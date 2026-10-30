<?php
namespace App\Tests\Ciw;
use App\Ciw\{PdoCiwSqlGrantReader,CiwSqlConnectionConfiguration};
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class PdoCiwSqlGrantReaderTest extends TestCase
{
    public function testReadsFixedSystemWithParametersAndActiveAccount():void
    {
        $sqls=[];$params=[];$pdo=$this->createMock(\PDO::class);
        $user=$this->createMock(\PDOStatement::class);$grants=$this->createMock(\PDOStatement::class);
        $pdo->expects(self::exactly(2))->method('prepare')->willReturnCallback(function($sql)use(&$sqls,$user,$grants){$sqls[]=$sql;return count($sqls)===1?$user:$grants;});
        $user->expects(self::once())->method('execute')->with(['thiago.cassimiro']);
        $user->method('fetchAll')->willReturn([['active'=>'Y']]);
        $grants->expects(self::once())->method('execute')->willReturnCallback(function($p)use(&$params){$params=$p;return true;});
        $grants->method('fetchAll')->willReturn([['access'=>'Y','update'=>'N']]);
        $reader=new PdoCiwSqlGrantReader($this->createStub(CiwSqlConnectionConfiguration::class),fn()=>$pdo);
        $result=$reader->read('thiago.cassimiro','form_pub_coloracoes');
        self::assertTrue($result['active']);self::assertSame(['form_pub_coloracoes','thiago.cassimiro'],$params);
        self::assertStringContainsString('m.cod_sistema = 18',$sqls[1]);self::assertStringContainsString('EXISTS',$sqls[1]);
        self::assertStringNotContainsString('thiago.cassimiro',implode(' ',$sqls));
        foreach($sqls as $sql){self::assertStringStartsWith('SELECT ',$sql);self::assertDoesNotMatchRegularExpression('/\b(pswd|priv_admin)\b/i',$sql);}
    }
    public function testInactiveAndMissingAccountsNeverReadGrants():void
    {
        foreach([[],[['active'=>'N']],[['active'=>null]]] as $rows){
            $pdo=$this->createMock(\PDO::class);$s=$this->createStub(\PDOStatement::class);
            $pdo->expects(self::once())->method('prepare')->willReturn($s);$s->method('fetchAll')->willReturn($rows);
            $r=new PdoCiwSqlGrantReader($this->createStub(CiwSqlConnectionConfiguration::class),fn()=>$pdo);
            self::assertSame(['active'=>false,'grants'=>[]],$r->read('test','grid_pub_coloracoes'));
        }
    }
    public function testAmbiguityAndConnectionErrorsAreSanitized():void
    {
        foreach([new \PDOException('password=synthetic_secret'),[['active'=>'Y'],['active'=>'Y']]] as $case){
            $pdo=$this->createStub(\PDO::class);$s=$this->createStub(\PDOStatement::class);$pdo->method('prepare')->willReturn($s);
            if(is_array($case))$s->method('fetchAll')->willReturn($case);
            $r=new PdoCiwSqlGrantReader($this->createStub(CiwSqlConnectionConfiguration::class),function()use($case,$pdo){if($case instanceof \Throwable)throw $case;return $pdo;});
            try{$r->read('test','grid_pub_coloracoes');self::fail('accepted');}catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());self::assertStringNotContainsString('synthetic_secret',$e->getMessage());self::assertNull($e->getPrevious());}
        }
    }
}
