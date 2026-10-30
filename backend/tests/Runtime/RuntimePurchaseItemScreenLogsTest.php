<?php
namespace App\Tests\Runtime;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{PermissionResolver,ProgramCustomizationResolver,RuntimeMetadataSanitizer,ScreenDefinitionService,StructuralIntegrityService,UserLayoutService};
use Symfony\Component\HttpFoundation\{Request,RequestStack};
use PHPUnit\Framework\TestCase;
final class RuntimePurchaseItemScreenLogsTest extends TestCase
{
    public function testNewHeaderReceivesNativeRecordEventsAndOnlyItsOwnDeletionShortcut(): void {
        $screens=[];foreach(['ciw.proc-compra-itens','admin.transacoes','admin.logs-transacoes']as $id)$screens[$id]=(new ScreenDefinition())->setScreenId($id)->setStatus('published')->setDefinition(['pageType'=>'crud','program'=>['entity'=>$id==='ciw.proc-compra-itens'?'proc_compra_itens_progress':'test'],'crud'=>['form'=>['logs'=>['enabled'=>false]]]]);
        $repo=$this->createStub(ScreenDefinitionRepository::class);$repo->method('findPublishedByScreenId')->willReturnCallback(static fn($id)=>$screens[$id]??null);$request=new Request();$request->headers->set('X-Runtime-Groups','admin');$stack=new RequestStack();$stack->push($request);$custom=$this->createStub(ProgramCustomizationResolver::class);$custom->method('resolve')->willReturn(null);$layouts=$this->createStub(UserLayoutService::class);$layouts->method('buildUserLayout')->willReturn([]);
        $service=new ScreenDefinitionService($repo,new RuntimeMetadataSanitizer(),new PermissionResolver($stack),$layouts,$custom,$this->createStub(StructuralIntegrityService::class));$before=$screens['ciw.proc-compra-itens']->getDefinition();$d=$service->getDefinition('ciw.proc-compra-itens');self::assertSame('recordEvents',$d['crud']['form']['logs']['mode']);self::assertSame('runtime.transaction-events',$d['crud']['form']['logs']['documentId']);self::assertSame('ciw.proc-compra-itens',$d['program']['logs']['screenId']);self::assertSame('deletions',$d['program']['logs']['mode']);self::assertSame($before,$screens['ciw.proc-compra-itens']->getDefinition());
    }
}
