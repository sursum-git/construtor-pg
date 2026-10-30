<?php
namespace App\Ciw;
use App\Entity\{AuthUser,AuthUserSubscriber,RuntimeUserSession};
use App\Repository\{AuthUserRepository,AuthUserSubscriberRepository,AuthSubscriberRepository,AuthProviderConfigRepository};
use App\Runtime\{StructuralIntegrityService,RuntimeTransactionService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
class CiwIdentityRegistration
{
    private const BINDING=['ciwAuthorization'=>['version'=>1,'subject'=>'thiago.cassimiro','system'=>18]];
    public function __construct(private readonly AuthUserRepository $users,private readonly AuthUserSubscriberRepository $links,private readonly AuthSubscriberRepository $subscribers,private readonly AuthProviderConfigRepository $providers,private readonly EntityManagerInterface $em,private readonly StructuralIntegrityService $integrity,private readonly RuntimeTransactionService $transactions,private readonly CiwProgressIdentityClientInterface $identity,private readonly CiwSqlGrantReaderInterface $grants,private readonly CiwColoracoesPolicy $policy){}
    public function register():array
    {
        $provider=$this->providers->findOneBy(['code'=>'ima-user-web']);$sub=$this->subscribers->findEnabledByCode('default');
        if(!$provider||!$provider->isEnabled()||$provider->getType()!=='ima-user-web'||!$sub)throw new RuntimeHttpException('CIW_REGISTRATION_UNAVAILABLE','Cadastro CIW indisponivel.',503);
        $this->integrity->assertAuthProvider($provider);$this->integrity->assertAuthSubscriber($sub);
        $user=$this->users->findOneByTenantAndUsername('default','tcassimiro');$links=$this->links->findBy(['userTenantId'=>'default','username'=>'tcassimiro']);
        if($user||$links){
            if(!$user||$user->getTenantId()!=='default'||$user->getUsername()!=='tcassimiro'||$user->getAuthSource()!=='ima-user-web'||$user->getStatus()!=='active'||$user->getPasswordHash()!==null||$user->getGroups()!==[]||$user->getPermissions()!==[]||count($links)!==1)$this->conflict();
            $link=$links[0];if(!$link->isEnabled()||!$link->isDefaultSubscriber()||$link->getSubscriberCode()!=='default'||$link->getMetadata()!==self::BINDING||$link->getPermissionOverrides()!==[])$this->conflict();
            $this->integrity->assertAuthUserSubscriber($link);
        }
        // Prova tecnica de existencia/ativo; nao cria sessao de login nem simula credenciais.
        $probe=(new RuntimeUserSession())->setTenantId('default')->setUserId('tcassimiro')->setSessionId('ciw-registration-preflight')->setSessionProperties(['authentication'=>['provider'=>'ima-user-web']]);
        $this->identity->assertActive($probe);$rows=$this->grants->read('thiago.cassimiro','grid_pub_coloracoes');
        if(!$rows['active']||!$this->policy->allows('read',$rows['grants']))throw new RuntimeHttpException('CIW_REGISTRATION_UNAVAILABLE','Cadastro CIW indisponivel.',503);
        if($user)return ['username'=>'tcassimiro','subscriber'=>'default','created'=>false,'unchanged'=>true];
        try{
            return $this->em->wrapInTransaction(function():array{
                $this->transactions->beginOperational(['source'=>'cli','screenId'=>'system.auth.users','endpointId'=>'configure-ciw-identity','operation'=>'auth.ciw.identity.configure','entityCode'=>'auth_user']);
                $user=(new AuthUser())->setTenantId('default')->setUsername('tcassimiro')->setDisplayName('tcassimiro')->setAuthSource('ima-user-web')->setPasswordHash(null)->setStatus('active')->setGroups([])->setPermissions([])->setForcePasswordChange(false);
                $link=(new AuthUserSubscriber())->setUserTenantId('default')->setUsername('tcassimiro')->setSubscriberCode('default')->setDefaultSubscriber(true)->setEnabled(true)->setMetadata(self::BINDING)->setPermissionOverrides([]);
                $this->em->persist($user);$this->em->persist($link);$this->em->flush();$this->integrity->signAuthUserSubscriber($link);
                $this->transactions->log('auth.ciw.identity.configured','Vinculo CIW cadastrado.',metadata:['username'=>'tcassimiro','subscriber'=>'default','system'=>18,'before'=>null,'after'=>self::BINDING]);
                $this->transactions->success(['username'=>'tcassimiro','subscriber'=>'default']);
                return ['username'=>'tcassimiro','subscriber'=>'default','created'=>true,'unchanged'=>false];
            });
        }catch(UniqueConstraintViolationException){$this->conflict();}
        catch(\Throwable){throw new RuntimeHttpException('CIW_REGISTRATION_FAILED','Nao foi possivel cadastrar o vinculo CIW.',503);}
        finally{$this->transactions->clear();}
    }
    private function conflict():never{throw new RuntimeHttpException('CIW_REGISTRATION_CONFLICT','Conta ou vinculo existente requer revisao.',409);}
}
