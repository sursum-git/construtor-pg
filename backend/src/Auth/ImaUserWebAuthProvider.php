<?php
namespace App\Auth;
use App\Entity\AuthProviderConfig;
use App\Repository\AuthUserRepository;
use App\Runtime\RuntimeHttpException;
class ImaUserWebAuthProvider implements AuthProviderInterface
{
    public function __construct(private readonly AuthUserRepository $users,private readonly ImaUserWebClientInterface $client) {}
    public function supports(AuthProviderConfig $config): bool
    { return $config->getType()==='ima-user-web'; }
    public function authenticate(AuthProviderConfig $config,array $credentials): AuthenticatedUser
    {
        $tenant=$credentials['tenantId']??'default';
        $username=$credentials['username']??null;
        $password=$credentials['password']??null;
        if (!$this->supports($config) || !$config->isEnabled() || $config->getCode()!=='ima-user-web'
            || !is_string($tenant) || !preg_match('/\A[A-Za-z0-9_.:-]{1,80}\z/',$tenant)
            || !is_string($username) || !mb_check_encoding($username,'UTF-8') || trim($username)==='' || mb_strlen($username)>160
            || !is_string($password) || !mb_check_encoding($password,'UTF-8') || strlen($password)<1 || strlen($password)>1024) $this->deny();
        $user=$this->users->findOneByTenantAndUsername($tenant,$username);
        if (!$user || $user->getTenantId()!==$tenant || $user->getStatus()!=='active'
            || $user->getAuthSource()!==$config->getCode()
            || mb_strtolower($user->getUsername())!==mb_strtolower($username)) $this->deny();
        try {
            $login=$this->client->authenticate($username,$password);
        } finally {
            unset($password,$credentials['password']);
        }
        if (mb_strtolower($login)!==mb_strtolower($user->getUsername())) $this->deny();
        return new AuthenticatedUser(
            tenantId:$user->getTenantId(),userId:$user->getUsername(),username:$user->getUsername(),
            displayName:$user->getDisplayName(),email:$user->getEmail(),
            groups:$user->getGroups(),permissions:$user->getPermissions(),source:$config->getCode(),
        );
    }
    private function deny(): never
    { throw new RuntimeHttpException('INVALID_CREDENTIALS','Usuario ou senha invalidos.',401); }
}
