<?php
namespace App\Runtime;

/** One immutable context is pinned before all HTTP calls and snapshots of an operation. */
class ApiCompanyContext
{
    public function __construct(public readonly string $moduleId,public readonly string $sourceCode,public readonly string $mode,
        public readonly int $selectionRevision,public readonly array $binding,public readonly array $mapping,public readonly array $adapters=[]) {}
    public function trustedValues():array
    {return array_intersect_key($this->binding,array_flip(['externalCompanyId','ems2Code','ems5Code']));}
    public function auditMetadata():array
    {
        return ['version'=>1,'source'=>'authenticated-api-company','moduleId'=>$this->moduleId,'sourceCode'=>$this->sourceCode,'mode'=>$this->mode,
            'key'=>$this->binding['key'],'companyCode'=>$this->binding['companyCode'],'externalCompanyId'=>$this->binding['externalCompanyId'],
            'ems2Code'=>$this->binding['ems2Code'],'ems5Code'=>$this->binding['ems5Code'],'bindingRevision'=>$this->binding['revision'],'selectionRevision'=>$this->selectionRevision];
    }
}
