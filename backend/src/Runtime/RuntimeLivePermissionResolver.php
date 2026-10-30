<?php
namespace App\Runtime;

use App\Auth\{AuthService,AuthenticatedSessionResolver};
use Symfony\Component\HttpFoundation\RequestStack;

/** Native permission rules over persisted signed access, without changing the login snapshot. */
class RuntimeLivePermissionResolver
{
    public function __construct(private readonly PermissionResolver $permissions,private readonly ?AuthService $auth=null,
        private readonly ?AuthenticatedSessionResolver $sessions=null) {}
    public function resolve():PermissionResolver
    {
        $session=$this->sessions?->resolve();
        if(!$this->auth||!$session)return $this->permissions;
        $snapshot=$session->getPermissionSnapshot();
        $user=$this->auth->resolveLiveSubscriberAccess((string)($snapshot['tenantId']??$session->getTenantId()),
            (string)($snapshot['user']['username']??$session->getUserId()),$session->getTenantId());
        return new class($user?->toPayload()??['groups'=>[],'permissions'=>[]],$session->getTenantId())extends PermissionResolver{
            public function __construct(private array $payload,private string $tenant){parent::__construct(new RequestStack());}
            public function getCurrentUserPayload():array{return $this->payload;}
            public function getTenantId():string{return $this->tenant;}
        };
    }
}
