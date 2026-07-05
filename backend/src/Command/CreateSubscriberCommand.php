<?php

namespace App\Command;

use App\Auth\PasswordPolicy;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUser;
use App\Entity\AuthUserSubscriber;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:subscriber:create', description: 'Cria ou atualiza um assinante e opcionalmente o administrador inicial.')]
class CreateSubscriberCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthSubscriberRepository $subscribers,
        private readonly AuthUserRepository $users,
        private readonly AuthUserSubscriberRepository $userSubscribers,
        private readonly StructuralIntegrityService $integrity,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Codigo unico do assinante.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nome do assinante.')
            ->addOption('document', null, InputOption::VALUE_REQUIRED, 'Documento do assinante.')
            ->addOption('principal', null, InputOption::VALUE_NONE, 'Marca o assinante como principal e desmarca os demais.')
            ->addOption('disabled', null, InputOption::VALUE_NONE, 'Cria ou atualiza o assinante ja desabilitado.')
            ->addOption('user-tenant-id', null, InputOption::VALUE_REQUIRED, 'Tenant do usuario administrativo.', 'default')
            ->addOption('admin-username', null, InputOption::VALUE_REQUIRED, 'Usuario administrador inicial.')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Senha do usuario administrador inicial.')
            ->addOption('admin-password-env', null, InputOption::VALUE_REQUIRED, 'Nome da variavel de ambiente com a senha do administrador inicial.')
            ->addOption('admin-display-name', null, InputOption::VALUE_REQUIRED, 'Nome de exibicao do administrador.')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email do administrador.')
            ->addOption('no-admin-default', null, InputOption::VALUE_NONE, 'Nao marca este assinante como padrao do administrador.')
            ->addOption('force-password-change', null, InputOption::VALUE_NONE, 'Marca o administrador para trocar a senha no primeiro acesso.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = $this->requiredTrimmedOption($input, 'code');
        $name = $this->requiredTrimmedOption($input, 'name');
        if ($code === null || $name === null) {
            $io->error('Informe pelo menos --code e --name.');
            return Command::INVALID;
        }
        $document = $this->nullableTrimmedOption($input, 'document');
        $principal = $input->getOption('principal') === true;
        $enabled = $input->getOption('disabled') !== true;
        $changedSubscribers = [];
        $changedLinks = [];

        if ($principal) {
            $changedSubscribers = array_merge($changedSubscribers, $this->demoteOtherPrincipals($code));
        }

        $subscriber = $this->subscribers->findOneBy(['code' => $code]) ?? new AuthSubscriber();
        $subscriber
            ->setCode($code)
            ->setName($name)
            ->setDocument($document)
            ->setPrincipal($principal)
            ->setEnabled($enabled)
            ->setMetadata([
                'source' => 'subscriber-create-command',
            ]);
        $this->entityManager->persist($subscriber);

        $adminUsername = $this->nullableTrimmedOption($input, 'admin-username');
        if ($adminUsername !== null) {
            $result = $this->upsertAdmin($input, $subscriber, $adminUsername, $changedLinks);
            if ($result !== null) {
                $io->error($result);
                return Command::FAILURE;
            }
        }

        $this->entityManager->flush();
        foreach (array_merge([$subscriber], $changedSubscribers) as $changedSubscriber) {
            $this->integrity->signAuthSubscriber($changedSubscriber, [
                'source' => 'subscriber-create-command',
            ]);
        }
        foreach ($changedLinks as $access) {
            $this->integrity->signAuthUserSubscriber($access, [
                'source' => 'subscriber-create-command',
            ]);
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            'Assinante %s (%s) preparado com sucesso.',
            $subscriber->getName(),
            $subscriber->getCode()
        ));

        return Command::SUCCESS;
    }

    private function upsertAdmin(InputInterface $input, AuthSubscriber $subscriber, string $adminUsername, array &$changedLinks): ?string
    {
        $tenantId = trim((string) $input->getOption('user-tenant-id')) ?: 'default';
        $password = $this->resolveAdminPassword($input);
        $displayName = $this->nullableTrimmedOption($input, 'admin-display-name');
        $email = $this->nullableTrimmedOption($input, 'admin-email');
        $defaultSubscriber = $input->getOption('no-admin-default') !== true;
        $forcePasswordChange = $input->getOption('force-password-change') === true;

            $user = $this->users->findOneByTenantAndUsername($tenantId, $adminUsername);
        $isNewUser = $user === null;
        if ($user === null) {
            if ($password === null) {
                return 'Informe --admin-password-env para criar um novo administrador sem expor a senha nos argumentos do processo.';
            }

            $user = new AuthUser();
            $user
                ->setTenantId($tenantId)
                ->setUsername($adminUsername)
                ->setStatus('active')
                ->setGroups(['admin'])
                ->setPermissions(['*'])
                ->setAuthSource('local');
        }

        if ($displayName !== null) {
            $user->setDisplayName($displayName);
        }
        if ($email !== null) {
            $user->setEmail($email);
        }
        if ($password !== null) {
            $passwordPolicy = PasswordPolicy::evaluateInitialAdminPassword($password, $subscriber->getCode(), $adminUsername);
            if ($passwordPolicy['status'] === 'error') {
                return $passwordPolicy['message'];
            }
            $user->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
        } elseif ($isNewUser) {
            return 'Nao foi possivel criar administrador sem senha inicial.';
        }
        if ($forcePasswordChange || $isNewUser) {
            $user->setForcePasswordChange(true);
        }
        $this->entityManager->persist($user);

        $access = $this->userSubscribers->findOneBy([
            'userTenantId' => $tenantId,
            'username' => AuthUser::normalizeUsername($adminUsername),
            'subscriberCode' => $subscriber->getCode(),
        ]) ?? new AuthUserSubscriber();
        $access
            ->setUserTenantId($tenantId)
            ->setUsername($adminUsername)
            ->setSubscriberCode($subscriber->getCode())
            ->setDefaultSubscriber($defaultSubscriber)
            ->setEnabled(true)
            ->setPermissionOverrides([])
            ->setMetadata([
                'source' => 'subscriber-create-command',
            ]);
        $this->entityManager->persist($access);
        $changedLinks[] = $access;

        if ($defaultSubscriber) {
            $changedLinks = array_merge($changedLinks, $this->demoteOtherUserDefaults($tenantId, $adminUsername, $subscriber->getCode()));
        }

        return null;
    }

    private function demoteOtherPrincipals(string $currentCode): array
    {
        $changed = [];
        foreach ($this->subscribers->findAll() as $subscriber) {
            if ($subscriber->getCode() !== $currentCode && $subscriber->isPrincipal()) {
                $subscriber->setPrincipal(false);
                $this->entityManager->persist($subscriber);
                $changed[] = $subscriber;
            }
        }

        return $changed;
    }

    private function demoteOtherUserDefaults(string $tenantId, string $username, string $currentSubscriberCode): array
    {
        $changed = [];
        foreach ($this->userSubscribers->findEnabledForUser($tenantId, $username) as $access) {
            if ($access->getSubscriberCode() !== $currentSubscriberCode && $access->isDefaultSubscriber()) {
                $access->setDefaultSubscriber(false);
                $this->entityManager->persist($access);
                $changed[] = $access;
            }
        }

        return $changed;
    }

    private function requiredTrimmedOption(InputInterface $input, string $name): ?string
    {
        $value = trim((string) $input->getOption($name));
        return $value === '' ? null : $value;
    }

    private function nullableTrimmedOption(InputInterface $input, string $name): ?string
    {
        $value = trim((string) $input->getOption($name));
        return $value === '' ? null : $value;
    }

    private function resolveAdminPassword(InputInterface $input): ?string
    {
        $password = $this->nullableTrimmedOption($input, 'admin-password');
        if ($password !== null) {
            return $password;
        }

        $envName = $this->nullableTrimmedOption($input, 'admin-password-env');
        if ($envName === null) {
            return null;
        }

        $envValue = $_SERVER[$envName] ?? $_ENV[$envName] ?? getenv($envName) ?: '';
        $envValue = trim((string) $envValue);

        return $envValue === '' ? null : $envValue;
    }
}
