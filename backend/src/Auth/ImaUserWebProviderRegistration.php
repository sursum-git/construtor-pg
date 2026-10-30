<?php
namespace App\Auth;
use App\Entity\AuthProviderConfig;
use App\Repository\AuthProviderConfigRepository;
use App\Runtime\{StructuralIntegrityService,RuntimeTransactionService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
class ImaUserWebProviderRegistration
{
    public function __construct(
        private readonly AuthProviderConfigRepository $providers,
        private readonly EntityManagerInterface $entityManager,
        private readonly StructuralIntegrityService $integrity,
        private readonly RuntimeTransactionService $transactions,
        private readonly ImaUserWebProviderConfiguration $configuration,
    ) {}
    public function register(bool $enable=false): AuthProviderConfig
    {
        $provider=$this->providers->findOneBy(['code'=>'ima-user-web']);
        if ($provider && $provider->getType()!=='ima-user-web') {
            throw new RuntimeHttpException('AUTH_PROVIDER_CONFLICT','O codigo do provedor ja possui outra configuracao.',409);
        }
        if ($provider && (!$enable || $provider->isEnabled())) return $provider;
        if ($enable) $this->configuration->resolve();
        try {
            return $this->entityManager->wrapInTransaction(function() use($provider,$enable): AuthProviderConfig {
                $before=$provider?['code'=>$provider->getCode(),'type'=>$provider->getType(),'enabled'=>$provider->isEnabled()]:null;
                $this->transactions->beginOperational([
                    'source'=>'cli','screenId'=>'system.auth.providers','endpointId'=>'configure-ima-user-web',
                    'operation'=>'auth.provider.configure','entityCode'=>'auth_provider_config',
                ]);
                $cfg=$provider??(new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')
                    ->setName('IMA — usuário Progress')->setConfig([]);
                $cfg->setEnabled($enable);
                $this->entityManager->persist($cfg);$this->entityManager->flush();
                $this->integrity->signAuthProvider($cfg);
                $this->transactions->log('auth.provider.configured','Provedor IMA configurado.',metadata:[
                    'before'=>$before,'after'=>['code'=>$cfg->getCode(),'type'=>$cfg->getType(),'enabled'=>$cfg->isEnabled()],
                ]);
                $this->transactions->success(['provider'=>'ima-user-web','enabled'=>$enable]);
                return $cfg;
            });
        } catch (\Throwable) {
            // A transacao de banco foi desfeita; nao registrar detalhes da excecao.
            throw new RuntimeHttpException('AUTH_PROVIDER_CONFIGURATION_FAILED','Nao foi possivel configurar o provedor IMA.',503);
        } finally {$this->transactions->clear();}
    }
}
