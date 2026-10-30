<?php
namespace App\Tests\Runtime;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{PermissionResolver,ProgramCustomizationResolver,RuntimeMetadataSanitizer,ScreenDefinitionService,StructuralIntegrityService,UserLayoutService};
use Symfony\Component\HttpFoundation\{Request,RequestStack};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseOrderScreenLogsTest extends TestCase
{
    public function testNewHeaderReceivesNativeRecordEventsAndOnlyItsOwnDeletionShortcut(): void {
        $screens=[];foreach(['ciw.processos-compra-med','admin.transacoes','admin.logs-transacoes']as $id)$screens[$id]=(new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition(['pageType'=>'crud','program'=>['entity'=>$id==='ciw.processos-compra-med'?'procs_compra_med_progress':'test'],'crud'=>['form'=>['logs'=>['enabled'=>false]]]]);
        $repo=$this->createStub(ScreenDefinitionRepository::class);$repo->method('findPublishedByScreenId')->willReturnCallback(static fn($id)=>$screens[$id]??null);$request=new Request();$request->headers->set('X-Runtime-Groups','admin');$stack=new RequestStack();$stack->push($request);$custom=$this->createStub(ProgramCustomizationResolver::class);$custom->method('resolve')->willReturn(null);$layouts=$this->createStub(UserLayoutService::class);$layouts->method('buildUserLayout')->willReturn([]);
        $service=new ScreenDefinitionService($repo,new RuntimeMetadataSanitizer(),new PermissionResolver($stack),$layouts,$custom,$this->createStub(StructuralIntegrityService::class));$before=$screens['ciw.processos-compra-med']->getDefinition();$d=$service->getDefinition('ciw.processos-compra-med');self::assertSame('recordEvents',$d['crud']['form']['logs']['mode']);self::assertSame('runtime.transaction-events',$d['crud']['form']['logs']['documentId']);self::assertSame('ciw.processos-compra-med',$d['program']['logs']['screenId']);self::assertSame('deletions',$d['program']['logs']['mode']);self::assertSame($before,$screens['ciw.processos-compra-med']->getDefinition());
    }
}
