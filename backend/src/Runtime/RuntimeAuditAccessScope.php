<?php
namespace App\Runtime;

class RuntimeAuditAccessScope
{
    /** @param list<string> $screenIds @param list<string>|null $allowedCompanyKeys */
    public function __construct(public readonly string $tenantId='',public readonly bool $unrestricted=false,public readonly array $screenIds=[],public readonly ?array $allowedCompanyKeys=null) {}
}
