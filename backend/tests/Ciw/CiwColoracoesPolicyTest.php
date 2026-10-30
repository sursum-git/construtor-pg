<?php
namespace App\Tests\Ciw;
use App\Ciw\CiwColoracoesPolicy;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class CiwColoracoesPolicyTest extends TestCase
{
    public function testApplicationsAndEachRequiredFlag(): void
    {
        $p=new CiwColoracoesPolicy();
        foreach(['read'=>'','get'=>'','create'=>'insert','update'=>'update','delete'=>'delete','export'=>'export','print'=>'print'] as $action=>$flag) {
            self::assertSame(in_array($action,['read','export','print'])?'grid_pub_coloracoes':'form_pub_coloracoes',$p->applicationFor($action));
            self::assertFalse($p->allows($action,[]));
            self::assertFalse($p->allows($action,[['access'=>'N',$flag=>'Y']]));
            self::assertTrue($p->allows($action,[['access'=>'Y',$flag=>'Y']]));
            if($flag!=='') foreach(['N',null,'','y',true] as $bad) self::assertFalse($p->allows($action,[['access'=>'Y',$flag=>$bad]]));
        }
    }
    public function testUnionIsIndependentOfOrderAndDuplicates(): void
    {
        $p=new CiwColoracoesPolicy();$rows=[['access'=>'Y','update'=>'N'],['access'=>'N','update'=>'Y']];
        self::assertTrue($p->allows('update',$rows));self::assertTrue($p->allows('update',array_reverse($rows)));
        self::assertTrue($p->allows('update',array_merge($rows,$rows)));
    }
    public function testUnknownActionIsRejected(): void
    {
        $this->expectException(RuntimeHttpException::class);(new CiwColoracoesPolicy())->applicationFor('admin');
    }
}
