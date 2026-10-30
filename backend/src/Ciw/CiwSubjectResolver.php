<?php
namespace App\Ciw;
use App\Entity\RuntimeUserSession;
use App\Repository\{AuthUserRepository,AuthUserSubscriberRepository,AuthSubscriberRepository};
use App\Runtime\{StructuralIntegrityService,RuntimeHttpException};
class CiwSubjectResolver
{
    public function __construct(private readonly AuthUserRepository $users,private readonly AuthUserSubscriberRepository $links,private readonly AuthSubscriberRepository $subscribers,private readonly StructuralIntegrityService $integrity){}
    public function resolve(RuntimeUserSession $session):string
    {
        $p=$session->getSessionProperties();
        if(($p['authentication']['provider']??null)!=='ima-user-web')throw new RuntimeHttpException('CIW_AUTH_SOURCE_DENIED','Origem de autenticacao nao permitida.',403);
        $tenant=$session->getTenantId();$current=$p['subscriber']['current']??null;$subscriber=is_array($current)?($current['code']??$current['id']??null):null;
        if(!is_string($subscriber)||$subscriber===''||$tenant!=='default'||$subscriber!=='default')$this->deny();
        $user=$this->users->findOneByTenantAndUsername($tenant,$session->getUserId());
        if(!$user||$user->getStatus()!=='active'||$user->getAuthSource()!=='ima-user-web')$this->deny();
        $link=$this->links->findOneEnabledForUserAndSubscriber($tenant,$user->getUsername(),$subscriber);
        $sub=$this->subscribers->findOneBy(['code'=>$subscriber]);
        if(!$link||!$link->isEnabled()||$link->getUserTenantId()!==$tenant||$link->getUsername()!==$user->getUsername()||$link->getSubscriberCode()!==$subscriber||!$sub||!$sub->isEnabled()||$sub->getCode()!==$subscriber)$this->deny();
        $this->integrity->assertAuthSubscriber($sub);$this->integrity->assertAuthUserSubscriber($link);
        $binding=$link->getMetadata()['ciwAuthorization']??null;
        if(!is_array($binding)||($binding['version']??null)!==1||($binding['system']??null)!==18||!is_string($binding['subject']??null)||!preg_match('/\A[A-Za-z0-9_.@-]{1,160}\z/',$binding['subject']))$this->deny();
        return $binding['subject'];
    }
    private function deny():never{throw new RuntimeHttpException('CIW_IDENTITY_NOT_LINKED','Vinculo CIW ausente ou indisponivel.',403);}
}
