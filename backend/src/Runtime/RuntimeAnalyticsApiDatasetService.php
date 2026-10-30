<?php
namespace App\Runtime;

use App\Entity\BuilderEntity;
use App\Repository\BuilderEntityRepository;

/** Registered read operations only. Identity and transport remain in the existing API gateway. */
class RuntimeAnalyticsApiDatasetService
{
    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeApiEntityActionService $actions,
    ) {}

    public function run(string $screenId, array $dataset, array $payload, ?string $tenantId): array
    {
        $stage = 'source';
        $invalid = static function () use (&$stage): never { throw new RuntimeHttpException('ANALYTICS_API_INVALID', 'Configuracao ou parametros da consulta API invalidos.', 422, ['stage'=>$stage]); };
        $source = $dataset['source'] ?? [];
        $code = $source['entityCode'] ?? '';
        if (($source['type'] ?? null) !== 'api' || !is_string($code) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,119}$/D', $code)
            || array_diff(array_keys($source), ['type','entityCode']) || !empty($dataset['joins']) || !empty($dataset['measures'])
            || !empty($dataset['dimensions']) || !empty($dataset['parameters']) || !empty($dataset['filters'])) { $invalid(); }
        $entity = $this->entities->findOneBy(['code'=>$code]);
        if (!$entity instanceof BuilderEntity || $entity->getEntityType() !== 'api' || empty($entity->getMetadata()['apiSource']['listEndpoint']['url'])) { $invalid(); }
        if (!$this->permissions->hasPermission($code.'.read')) {
            throw new RuntimeHttpException('ANALYTICS_API_FORBIDDEN', 'Sem permissao para consultar esta fonte.', 403);
        }
        $stage = 'parameters';
        // These are analytics presentation parameters, never external body/identity/operation overrides.
        // Dispatcher adds these tracing fields; they never select an entity or external operation here.
        if (array_diff(array_keys($payload), ['datasetId','viewId','take','skip','sort','parameters','forceLive','executionMode','entityCode','operation','programId','actionId','_runtimeEndpoint'])
            || !empty($payload['parameters'])) { $invalid(); }
        $stage = 'fields';
        $registered = [];
        foreach ($entity->getFields() as $field) { $registered[$field->getCode()] = $field; }
        $columns = [];
        foreach ($dataset['fields'] ?? [] as $spec) {
            $spec = is_string($spec) ? ['field'=>$spec] : $spec;
            if (!is_array($spec)) { $invalid(); }
            $name = $spec['field'] ?? $spec['id'] ?? '';
            if (!is_string($name) || !isset($registered[$name]) || isset($columns[$name]) || ($spec['id'] ?? $name) !== $name) { $invalid(); }
            $field = $registered[$name];
            $columns[$name] = ['field'=>$name,'id'=>$name,'label'=>$spec['label'] ?? $field->getLabel(),'title'=>$spec['label'] ?? $field->getLabel(),
                'type'=>$field->getDataType(),'role'=>'field','format'=>$spec['format'] ?? null];
        }
        if (!$columns) { $invalid(); }
        $stage = 'pagination';
        $limit = $dataset['limit'] ?? 1000;
        $take = $payload['take'] ?? $limit;
        $skip = $payload['skip'] ?? 0;
        if (!is_int($limit) || $limit < 1 || $limit > 5000 || !is_int($take) || $take < 1 || $take > $limit || !is_int($skip) || $skip < 0) { $invalid(); }
        $stage = 'sort';
        $sort = $payload['sort'] ?? $dataset['defaultSort'] ?? [];
        if (!is_array($sort) || !array_is_list($sort) || count($sort)>count($columns)) { $invalid(); }
        foreach ($sort as $order) {
            if (!is_array($order) || !isset($columns[$order['field'] ?? '']) || !in_array($order['dir'] ?? null,['asc','desc'],true)
                || array_diff(array_keys($order),['field','dir'])) { $invalid(); }
        }
        $stage = 'response';
        $result = ['data'=>[], 'total'=>null];
        do {
            $pageTake=min(500,$take-count($result['data']));
            $page=$this->actions->handle($screenId,'read',['entityCode'=>$code,'operation'=>'read'],['take'=>$pageTake,'skip'=>$skip+count($result['data']),'sort'=>$sort]);
            if (!isset($page['data']) || !is_array($page['data']) || !array_is_list($page['data']) || count($page['data'])>$pageTake
                || !is_int($page['total'] ?? null) || $page['total']<count($page['data'])
                || ($result['total']!==null && $result['total']!==$page['total'])) { $invalid(); }
            $result['total']=$page['total'];
            if (!$page['data'] && $skip+count($result['data'])<$result['total']) { $invalid(); }
            array_push($result['data'],...$page['data']);
        } while (count($result['data'])<$take && $skip+count($result['data'])<$result['total']);
        $stage = 'response_fields';
        $rows = [];
        foreach ($result['data'] as $row) {
            if (!is_array($row)) { $invalid(); }
            $safe = [];
            foreach ($columns as $name=>$column) {
                if (!array_key_exists($name,$row)) { $invalid(); }
                $value=$row[$name];
                if ($value !== null) {
                    $valid=match($column['type']) {
                        'integer' => is_int($value),
                        'decimal','currency','number' => (is_int($value)||is_float($value)) && is_finite((float)$value),
                        'boolean' => is_bool($value),
                        default => is_string($value),
                    };
                    if (!$valid) { $invalid(); }
                }
                $safe[$name]=$value;
            }
            $rows[]=$safe;
        }
        return ['data'=>$rows,'total'=>$result['total'],'columns'=>array_values($columns),'datasetId'=>(string)$dataset['id'],
            'generatedAt'=>(new \DateTimeImmutable())->format(DATE_ATOM),
            '_runtime'=>['analytics'=>['screenId'=>$screenId,'datasetId'=>$dataset['id'],'executionMode'=>'live','aggregated'=>false,'limit'=>$take,'skip'=>$skip]]];
    }
}
