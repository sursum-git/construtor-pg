<?php
namespace App\Runtime;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

class RuntimeAuditAccessPolicy
{
    public function __construct(private readonly PermissionResolver $permissions, private readonly PublishedModuleCatalog $modules,
        private readonly ?\App\Auth\AuthService $auth=null, private readonly ?\App\Auth\AuthenticatedSessionResolver $sessions=null,
        private readonly ?ApiCompanyBindingRegistry $companies=null, private readonly ?\Doctrine\DBAL\Connection $connection=null) {}

    public function resolve(): RuntimeAuditAccessScope
    {
        $permissions=$this->effectivePermissions();
        $tenant=$permissions->getTenantId();
        if($permissions->isAdministrator())return new RuntimeAuditAccessScope($tenant,true);
        if(!$permissions->hasPermission('ciw.audit.read'))return new RuntimeAuditAccessScope($tenant);
        $ids=[];foreach($this->modules->screenIds('ciw') as $id){
            if($this->canReadSource($id,$permissions))$ids[]=$id;
        }
        $companyKeys=null;
        if($this->companies){
            $companyKeys=[];foreach($this->companies->bindings($tenant,'ciw') as $binding){
                if($permissions->hasPermission($this->companies->permission('ciw',$binding['key'])))$companyKeys[]='ciw/'.$binding['key'];
            }
            $companyKeys=array_values(array_unique($companyKeys));
        }
        return new RuntimeAuditAccessScope($tenant,false,$ids,$companyKeys);
    }

    public function canViewScreen(string $sourceScreenId):bool
    {
        $scope=$this->resolve();
        return $scope->unrestricted || in_array($sourceScreenId,$scope->screenIds,true);
    }

    private function canReadSource(string $id,PermissionResolver $permissions):bool
    {
        $screen=$this->modules->screen($id);
        return $screen && $permissions->canReadScreen($screen)
            && ($permissions->applyDefinitionPermissions($screen->getDefinition())['permissions']['read']??true)!==false;
    }

    public function effectivePermissions(): PermissionResolver
    {
        return (new RuntimeLivePermissionResolver($this->permissions,$this->auth,$this->sessions))->resolve();
    }

    public function applyForCurrentUser(QueryBuilder $query,array $definition,string $alias='t'):void
    {
        if(!in_array($definition['entityCode']??'', ['runtime_transaction','runtime_transaction_log'],true))return;
        $this->apply($query,$definition,$this->resolve(),$alias);
    }

    public function apply(QueryBuilder $query,array $definition,RuntimeAuditAccessScope $scope,string $alias='t'):void
    {
        $entity=$definition['entityCode']??'';
        if(!in_array($entity,['runtime_transaction','runtime_transaction_log'],true))return;
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$alias))throw new \InvalidArgumentException('Invalid audit alias');
        if(!$scope->unrestricted&&!$scope->screenIds){$query->andWhere('1 = 0');return;}
        $query->setParameter('_audit_access_tenant',$scope->tenantId);
        $source=$entity==='runtime_transaction'?$alias:'_audit_parent';
        $terms=[$source.'.tenant_id = :_audit_access_tenant'];
        if(!$scope->unrestricted){
            $terms[]=$source.'.screen_id IN (:_audit_access_screens)';
            $query->setParameter('_audit_access_screens',$scope->screenIds,ArrayParameterType::STRING);
        }
        if(!$scope->unrestricted && $scope->allowedCompanyKeys!==null){
            $terms[]=$this->companyPredicate($query,$source,$scope->allowedCompanyKeys);
        }
        $predicate=implode(' AND ',$terms);
        $query->andWhere($entity==='runtime_transaction'?$predicate:
            'EXISTS (SELECT 1 FROM runtime_transaction _audit_parent WHERE _audit_parent.id = '.$alias.'.transaction_id AND '.$predicate.')');
    }

    private function companyPredicate(QueryBuilder $query,string $alias,array $allowed):string
    {
        if(!$this->connection)throw new RuntimeHttpException('AUDIT_ACCESS_POLICY_REQUIRED','Conexao da politica empresarial indisponivel.',503);
        $sqlite=$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        $column=$alias.'.request_context';
        $field=static fn(string $name):string=>$sqlite?"json_extract(".$column.", '$.apiCompany.".$name."')":$column."->'apiCompany'->>'".$name."'";
        $legacy="COALESCE(".$field('source').", '') <> 'authenticated-api-company'";
        $mode=$field('mode');$module=$field('moduleId');$version=$field('version');
        $owned='1 = 0';
        if($allowed){
            $query->setParameter('_audit_access_companies',$allowed,ArrayParameterType::STRING);
            $owned="(".$module." || '/' || ".$field('key').") IN (:_audit_access_companies)";
        }
        // Legacy records remain unassigned. Unknown/malformed future versions fail closed.
        return '('.$legacy.' OR (CAST('.$version." AS TEXT) = '1' AND ".$module." = 'ciw' AND (".$mode." = 'shared' OR (".$mode." IN ('company','record') AND ".$owned.'))))';
    }
}
