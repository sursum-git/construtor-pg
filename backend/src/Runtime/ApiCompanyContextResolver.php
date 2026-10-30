<?php
namespace App\Runtime;

use App\Builder\ApiCompanyContextValidator as Validator;
use App\Entity\RuntimeUserSession;
use App\Repository\BuilderEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class ApiCompanyContextResolver
{
    public function __construct(private readonly ApiCompanyBindingRegistry $registry,private readonly RuntimeSessionGuard $sessions,
        private readonly RuntimeLivePermissionResolver $live,private readonly BuilderEntityRepository $entities,
        private readonly PublishedModuleCatalog $modules,private readonly StructuralIntegrityService $integrity,private readonly EntityManagerInterface $em,private readonly ?\App\Repository\RuntimeEndpointRepository $endpoints=null) {}

    /** Public descriptor; no external identifiers, adapters or integration credentials. */
    public function describeScreen(string $screenId,array $definition):?array
    {
        $code=$definition['program']['entity']??null;
        if(!is_string($code)||$code==='')return null;
        $entity=$this->entities->findOneBy(['code'=>$code]);if(!$entity)return null;
        $this->em->refresh($entity);$this->integrity->assertBuilderEntity($entity);
        $config=Validator::validateEntityConfig($entity->getMetadata()['apiCompanyContext']??[],$entity->getEntityType());
        if(!$config)return null;
        if(!in_array($screenId,$this->modules->screenIds($config['moduleId']),true))$this->forbidden();
        if(!$this->endpoints)throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Registro de endpoints indisponivel.',503);
        $modes=[];
        foreach($this->endpoints->findBy(['screenId'=>$screenId,'enabled'=>true]) as $endpoint){
            $id=$endpoint->getEndpointId();
            $this->em->refresh($endpoint);$this->integrity->assertEndpoint($endpoint);
            if(!in_array($endpoint->getHandler(),['entity.api.crud','entity.api.readonly'],true))continue;
            $runtime=$endpoint->getConfig();if(($runtime['entityCode']??null)!==$code)$this->forbidden();
            $operation=$runtime['operation']??$id;$entry=Validator::operationEntry($config,$operation);
            if(!$entry)throw new RuntimeHttpException('API_COMPANY_OPERATION_UNCONFIGURED','Operacao sem contrato de empresa configurado.',422);
            $modes[$id]=['mode'=>$entry['mode'],'operation'=>$operation];
        }
        return $modes?['version'=>1,'moduleId'=>$config['moduleId'],'screenId'=>$screenId,'endpointModes'=>$modes]:null;
    }

    public function options(string $moduleId):array
    {
        Validator::key($moduleId);$session=$this->sessions->ensureActive(false);$state=$this->state($session,$moduleId);
        if(!$this->registry->moduleUsesApi($session->getTenantId(),$moduleId))return ['enabled'=>false,'items'=>[],'selected'=>null,'selectionRevision'=>$state['selectionRevision']??0];
        $bindings=$this->registry->bindings($session->getTenantId(),$moduleId);$permissions=$this->live->resolve();$items=[];
        foreach($bindings as $binding)if($permissions->hasPermission($this->registry->permission($moduleId,$binding['key'])))$items[$binding['key']]=['key'=>$binding['key'],'label'=>$binding['label']];
        $selected=null;
        if($state&&isset($items[$state['key']])&&hash_equals($state['bindingRevision'],$this->registry->revisionFingerprint($bindings,$state['key'])))
            $selected=$items[$state['key']]+['selectionRevision'=>$state['selectionRevision']];
        return ['enabled'=>(bool)$bindings,'items'=>array_values($items),'selected'=>$selected,'selectionRevision'=>$state['selectionRevision']??0];
    }

    public function select(string $moduleId,string $key,int $expectedSelectionRevision):array
    {
        Validator::key($moduleId);Validator::key($key);
        if($expectedSelectionRevision<0||$expectedSelectionRevision>=2147483647)$this->changed();
        $session=$this->sessions->ensureActive(false);
        return $this->em->wrapInTransaction(function()use($session,$moduleId,$key,$expectedSelectionRevision):array{
            $this->em->lock($session,LockMode::PESSIMISTIC_WRITE);$this->em->refresh($session);
            if($session->getStatus()!=='active')throw new RuntimeHttpException('SESSION_REVOKED','Sessao encerrada.',401);
            if(!$this->registry->moduleUsesApi($session->getTenantId(),$moduleId))throw new RuntimeHttpException('API_COMPANY_MODULE_UNAVAILABLE','Modulo sem integracao API.',422);
            $state=$this->state($session,$moduleId);if(($state['selectionRevision']??0)!==$expectedSelectionRevision)$this->changed();
            $bindings=$this->registry->bindings($session->getTenantId(),$moduleId);$binding=$this->registry->findEnabled($session->getTenantId(),$moduleId,$key);
            if(!$this->live->resolve()->hasPermission($this->registry->permission($moduleId,$key)))$this->forbidden();
            $properties=$session->getSessionProperties();$revision=$expectedSelectionRevision+1;
            $properties['apiCompanies'][$moduleId]=['key'=>$key,'bindingRevision'=>$this->registry->revisionFingerprint($bindings,$key),'selectionRevision'=>$revision];
            $session->setSessionProperties($properties);$this->em->flush();
            return ['key'=>$key,'label'=>$binding['label'],'selectionRevision'=>$revision];
        });
    }

    public function forOperation(string $screenId,string $entityCode,string $operation,?int $expectedSelectionRevision):?ApiCompanyContext
    {
        $entity=$this->entities->findOneBy(['code'=>$entityCode]);if(!$entity)return null;
        $this->em->refresh($entity);$this->integrity->assertBuilderEntity($entity);
        $metadata=$entity->getMetadata();$raw=$metadata['apiCompanyContext']??[];
        if(!is_array($raw))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Contexto empresarial invalido.',422);
        $config=Validator::validateEntityConfig($raw,$entity->getEntityType());if(!$config)return null;
        $moduleId=$config['moduleId'];$permissions=$this->live->resolve();$screen=$this->modules->screen($screenId);
        if(!in_array($screenId,$this->modules->screenIds($moduleId),true)||!$screen||($screen->getDefinition()['program']['entity']??'')!==$entityCode
            ||!$permissions->canReadScreen($screen)||($permissions->applyDefinitionPermissions($screen->getDefinition())['permissions']['read']??true)===false)$this->forbidden();
        $entry=Validator::operationEntry($config,$operation);
        if(!$entry)throw new RuntimeHttpException('API_COMPANY_OPERATION_UNCONFIGURED','Operacao sem contrato de empresa configurado.',422);
        if($entry['mode']==='shared')return null;
        $session=$this->sessions->ensureActive(false);$state=$this->state($session,$moduleId);
        if(!$state)throw new RuntimeHttpException('API_COMPANY_REQUIRED','Selecione a empresa da API.',422);
        if($expectedSelectionRevision===null||$expectedSelectionRevision!==$state['selectionRevision'])$this->changed();
        if(!$permissions->hasPermission($this->registry->permission($moduleId,$state['key'])))$this->forbidden();
        $bindings=$this->registry->bindings($session->getTenantId(),$moduleId);
        if(!hash_equals($state['bindingRevision'],$this->registry->revisionFingerprint($bindings,$state['key'])))$this->changed();
        $sourceCode=$metadata['apiBinding']['sourceCode']??null;
        if(!is_string($sourceCode))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','A integracao exige uma fonte API cadastrada.',422);
        $binding=$this->registry->findEnabled($session->getTenantId(),$moduleId,$state['key'],$sourceCode);
        $adapters=$this->registry->sourceConfig($sourceCode)['adapters']??[];
        $mapping=$adapters[$entry['adapter']]??null;
        if(!is_array($mapping))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Adaptador empresarial indisponivel.',422);
        return new ApiCompanyContext($moduleId,$sourceCode,$entry['mode'],$state['selectionRevision'],$binding,$mapping,$adapters);
    }

    public function authorizedKeys(string $moduleId):array
    {
        return array_column($this->options($moduleId)['items'],'key');
    }

    private function state(RuntimeUserSession $session,string $moduleId):array
    {
        $state=$session->getSessionProperties()['apiCompanies'][$moduleId]??[];
        if(!$state)return [];
        if(!is_array($state)||!is_string($state['key']??null)||!is_string($state['bindingRevision']??null)
            ||!is_int($state['selectionRevision']??null)||$state['selectionRevision']<1)
            throw new RuntimeHttpException('API_COMPANY_CONTEXT_INVALID','Contexto de empresa invalido. Selecione novamente.',409);
        return $state;
    }
    private function forbidden():never{throw new RuntimeHttpException('API_COMPANY_FORBIDDEN','Empresa nao autorizada para esta operacao.',403);}
    private function changed():never{throw new RuntimeHttpException('API_COMPANY_CONTEXT_CHANGED','A empresa ou sua configuracao mudou. Recarregue os dados.',409);}
}
