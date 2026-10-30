<?php
namespace App\Runtime;

use App\Builder\ApiCompanyContextValidator as Validator;
use App\Repository\{AuthSubscriberRepository,BuilderApiSourceRepository,BuilderEntityRepository};
use Doctrine\ORM\EntityManagerInterface;

class ApiCompanyBindingRegistry
{
    public function __construct(private readonly AuthSubscriberRepository $companies,private readonly BuilderApiSourceRepository $sources,
        private readonly BuilderEntityRepository $entities,private readonly PublishedModuleCatalog $modules,
        private readonly StructuralIntegrityService $integrity,private readonly EntityManagerInterface $em) {}

    public function moduleUsesApi(string $tenantId,string $moduleId):bool
    {
        Validator::key($moduleId);
        foreach($this->modules->screenIds($moduleId)as $id){
            $screen=$this->modules->screen($id);$code=$screen?->getDefinition()['program']['entity']??null;
            if(!is_string($code)||$code==='')continue;
            $entity=$this->entities->findOneBy(['code'=>$code]);
            if($entity){$this->em->refresh($entity);$this->integrity->assertBuilderEntity($entity);if($entity->getEntityType()==='api')return true;}
        }
        return false;
    }

    /** @return list<array> Returns server-only identifiers; use publicOptions for the UI. */
    public function bindings(string $tenantId,string $moduleId):array
    {
        Validator::key($moduleId);$result=[];$seen=[];$owners=[];
        foreach($this->companies->findEnabledOrdered()as $company){
            $this->em->refresh($company);if(!$company->isEnabled())continue;
            $this->integrity->assertAuthSubscriber($company);
            $bindings=$company->getMetadata()['apiCompanyBindings']??[];
            if(!is_array($bindings)||!array_is_list($bindings)||count($bindings)>256)throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Vinculos de empresa invalidos.',422);
            foreach($bindings as $raw){
                if(!is_array($raw))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Vinculo de empresa invalido.',422);
                $b=Validator::validateBinding($raw);
                if($b['ownerTenantId']!==$tenantId||$b['moduleId']!==$moduleId)continue;
                $identity=$b['key'].'/'.$b['apiSourceCode'];
                if(isset($seen[$identity]) || (isset($owners[$b['key']])&&$owners[$b['key']]!==$company->getCode()))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Vinculo de empresa duplicado.',422);
                $seen[$identity]=true;$owners[$b['key']]=$company->getCode();
                if(!$b['enabled'])continue;
                $config=$this->sourceConfig($b['apiSourceCode']);if(!$config)continue;
                $result[]=array_merge($b,['companyCode'=>$company->getCode(),'label'=>$company->getName(),'sourceContextHash'=>hash('sha256',json_encode($config,JSON_THROW_ON_ERROR))]);
            }
        }
        return $result;
    }

    public function findEnabled(string $tenantId,string $moduleId,string $key,?string $sourceCode=null):array
    {
        Validator::key($key);
        foreach($this->bindings($tenantId,$moduleId)as $b)if($b['key']===$key&&($sourceCode===null||$b['apiSourceCode']===$sourceCode))return $b;
        throw new RuntimeHttpException('API_COMPANY_UNAVAILABLE','Empresa nao disponivel para esta integracao.',403);
    }

    public function sourceConfig(string $sourceCode):array
    {
        $source=$this->sources->findOneBy(['code'=>$sourceCode]);if(!$source)return [];
        $this->em->refresh($source);$this->integrity->assertApiSource($source);
        if($source->getStatus()!=='active')return [];
        $raw=$source->getMetadata()['companyContext']??[];
        if(!is_array($raw))throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Contexto da fonte invalido.',422);
        if($raw && ($source->getMetadata()['providerType']??'generic')!=='generic')throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Contexto empresarial exige transporte API generico.',422);
        return Validator::validateSourceConfig($raw);
    }

    public function revisionFingerprint(array $bindings,string $key):string
    {
        $selected=[];foreach($bindings as $b)if($b['key']===$key){$copy=$b;unset($copy['label']);ksort($copy);$selected[$b['apiSourceCode']]=$copy;}
        if(!$selected)throw new RuntimeHttpException('API_COMPANY_UNAVAILABLE','Empresa nao disponivel.',403);
        ksort($selected);return hash('sha256',json_encode($selected,JSON_THROW_ON_ERROR));
    }

    public function permission(string $moduleId,string $key):string
    {return 'api.company.'.Validator::key($moduleId).'.'.Validator::key($key).'.use';}
}
