<?php
namespace App\Tests\Builder;
use App\Builder\ProgramBuilderService;
use App\Entity\BuilderEntity;
use App\Entity\BuilderField;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
if ($source=getenv('BUILDER_SERVICE_SOURCE')) {require_once $source;}
final class RowLinkIconsTest extends TestCase
{
    private function normalize(array $link): array
    {
        $r=new \ReflectionClass(ProgramBuilderService::class);$s=$r->newInstanceWithoutConstructor();
        $entity=(new BuilderEntity())->addField((new BuilderField())->setCode('despesa_id'));
        return $r->getMethod('normalizeRowLinks')->invoke($s,[$link],$entity);
    }
    private function link(): array {return ['id'=>'empresas','label'=>'Empresas','screenId'=>'ciw.empresas-despesa','parameters'=>[['field'=>'despesa_id','sourceField'=>'despesa_id']]];}
    public function testAcceptsExistingTextLink(): void {$l=$this->link();self::assertSame([$l],$this->normalize($l));}
    public function testPreservesValidIconOnlyMetadata(): void {$l=$this->link()+['icon'=>'buildings-outline','iconOnly'=>true];self::assertSame([$l],$this->normalize($l));}
    public function testRejectsInvalidIcon(): void {$this->expectException(RuntimeHttpException::class);$this->normalize($this->link()+['icon'=>'x" onclick="x']);}
    public function testRejectsIconOnlyWithoutIcon(): void {$this->expectException(RuntimeHttpException::class);$this->normalize($this->link()+['iconOnly'=>true]);}
    public function testRejectsNonBooleanIconOnly(): void {$this->expectException(RuntimeHttpException::class);$this->normalize($this->link()+['icon'=>'buildings-outline','iconOnly'=>'true']);}
}
