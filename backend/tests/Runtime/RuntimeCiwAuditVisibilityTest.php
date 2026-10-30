<?php
namespace App\Tests\Runtime;

use App\Entity\{ScreenDefinition,RuntimeEndpoint};
use App\Runtime\PermissionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class RuntimeCiwAuditVisibilityTest extends TestCase
{
    public function testSpecificGrantAllowsOnlyReadEndpointsOfNativeAuditScreens():void
    {
        $p=new class(new RequestStack()) extends PermissionResolver {
            public function getCurrentUserPayload():array{return ['groups'=>[],'permissions'=>['ciw.audit.read'=>true]];}
        };
        foreach(['admin.transacoes'=>'runtime_transaction','admin.logs-transacoes'=>'runtime_transaction_log']as $id=>$entity){
            $s=(new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition(['security'=>['userGroups'=>['admin']],'permissions'=>['read'=>true]]);
            self::assertTrue($p->canReadScreen($s));
            foreach(['read'=>true,'get'=>true,'delete'=>false,'create'=>false]as $op=>$allowed){
                $e=(new RuntimeEndpoint())->setScreenId($id)->setEndpointId($op)->setHandler('entity.crud')->setPermission('admin.read')->setConfig(['entityCode'=>$entity,'operation'=>$op]);
                self::assertSame($allowed,$p->canExecuteEndpoint($e));
            }
            $e->setEndpointId('read')->setConfig(['entityCode'=>'auth_user','operation'=>'read']);self::assertFalse($p->canExecuteEndpoint($e));
        }
        self::assertFalse($p->isAdministrator());
        self::assertFalse($p->canReadScreen((new ScreenDefinition())->setScreenId('admin.usuarios')->setDefinition(['security'=>['userGroups'=>['admin']]])));
    }
    public function testControlsFollowSourcePolicyInFormBatchAndProcess():void
    {
        foreach(['crud','batch','process']as $mode){
            foreach([true,false]as $allowed){
                $p=new class(new RequestStack()) extends PermissionResolver { public function getCurrentUserPayload():array{return ['groups'=>[],'permissions'=>['ciw.audit.read'=>true,'source.read'=>true]];} };
                $d=['screenId'=>'ciw.source','pageType'=>$mode==='process'?'process':'crud','program'=>['entity'=>$mode==='process'?'container_processos_progress':'source'],'permissions'=>['read'=>true], 'crud'=>['grid'=>['editing'=>['mode'=>$mode==='batch'?'batch':'form']]]];
                $screens=['ciw.source'=>(new ScreenDefinition())->setScreenId('ciw.source')->setStatus('published')->setDefinition($d)];
                foreach(['admin.transacoes','admin.logs-transacoes']as $id)$screens[$id]=(new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition(['screenId'=>$id,'security'=>['userGroups'=>['admin']],'permissions'=>['read'=>true]]);
                $repo=$this->createStub(\App\Repository\ScreenDefinitionRepository::class);$repo->method('findPublishedByScreenId')->willReturnCallback(fn($id)=>$screens[$id]??null);
                $custom=$this->createStub(\App\Runtime\ProgramCustomizationResolver::class);$custom->method('resolve')->willReturn(null);
                $policy=$this->createStub(\App\Runtime\RuntimeAuditAccessPolicy::class);$policy->method('canViewScreen')->willReturn($allowed);
                $svc=new \App\Runtime\ScreenDefinitionService($repo,new \App\Runtime\RuntimeMetadataSanitizer(),$p,$this->createStub(\App\Runtime\UserLayoutService::class),$custom,$this->createStub(\App\Runtime\StructuralIntegrityService::class),$policy);
                $result=$svc->getDefinition('ciw.source');
                self::assertSame($allowed,($mode==='process'?$result['logs']:($result['crud']['form']['logs']??[]))['enabled']??false);
                if($mode!=='process')self::assertSame($allowed,$result['program']['logs']['enabled']??false);
            }
        }
    }
}
