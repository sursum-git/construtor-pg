<?php
// Deterministic metadata boundary fixture: real factory, runtime enrichment and sanitizer.
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Admin\AdminCrudDefinitionFactory;
use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\{PermissionResolver,ProgramCustomizationResolver,RuntimeMetadataSanitizer,ScreenDefinitionService,StructuralIntegrityService,UserLayoutService};
use Symfony\Component\HttpFoundation\{Request,RequestStack};

$repo = new class extends ScreenDefinitionRepository {
    public function __construct() {}
    public function findPublishedByScreenId(string $screenId): ?ScreenDefinition {
        if ($screenId === 'ciw.tipos-anexo') {
            return (new ScreenDefinition())->setScreenId($screenId)->setStatus('published')->setDefinition([
                'schemaVersion'=>'1.0','pageType'=>'crud','screenId'=>$screenId,
                'program'=>['id'=>'audit-batch-test','module'=>'cadastros','entity'=>'tipos_anexo_test','title'=>'Tipos de anexo'],
                'permissions'=>['read'=>true,'create'=>true,'edit'=>true,'delete'=>true],
                'dataSource'=>['api'=>['read'=>['endpointId'=>'read','method'=>'POST'],'batch'=>['endpointId'=>'batch','method'=>'POST']]],
                'dataModel'=>['primaryKey'=>'tipo_anexo_id','fields'=>[
                    'tipo_anexo_id'=>['type'=>'integer','label'=>'ID','editable'=>false],
                    'descricao'=>['type'=>'string','label'=>'Descrição','editable'=>true]]],
                'crud'=>['query'=>['pageSize'=>20],
                    'grid'=>['editing'=>['mode'=>'batch'],'columns'=>[['field'=>'tipo_anexo_id','title'=>'ID'],['field'=>'descricao','title'=>'Descrição']]],
                    'form'=>['id'=>'audit-batch-test-form','mode'=>'popup','layout'=>'sections','logs'=>['enabled'=>false],
                        'sections'=>[['id'=>'geral','fields'=>[['field'=>'descricao']]]]]],
            ]);
        }
        $catalog = AdminCrudDefinitionFactory::screens();
        return isset($catalog[$screenId]) ? (new ScreenDefinition())->setScreenId($screenId)->setStatus('published')->setDefinition($catalog[$screenId]['definition']) : null;
    }
};
$layouts = new class extends UserLayoutService {
    public function __construct() {}
    public function buildUserLayout(string $screenId): array { return []; }
};
$custom = new class extends ProgramCustomizationResolver {
    public function __construct() {}
    public function resolve(string $screenId, array $baseDefinition): ?array { return null; }
};
$integrity = new class extends StructuralIntegrityService {
    public function __construct() {}
    public function assertScreen(ScreenDefinition $screen): void {}
};
$request = new Request(); $request->headers->set('X-Runtime-Groups', 'admin');
$stack = new RequestStack(); $stack->push($request);
$service = new ScreenDefinitionService($repo, new RuntimeMetadataSanitizer(), new PermissionResolver($stack), $layouts, $custom, $integrity);
echo json_encode(in_array('--business', $argv, true) ? ['batch'=>$service->getDefinition('ciw.tipos-anexo')] : ['transactions'=>$service->getDefinition('admin.transacoes'),'events'=>$service->getDefinition('admin.logs-transacoes')], JSON_THROW_ON_ERROR);
