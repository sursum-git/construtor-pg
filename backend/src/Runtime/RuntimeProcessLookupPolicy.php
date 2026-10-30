<?php
namespace App\Runtime;

/** Process selectors are read requests and use exactly the process context. */
final class RuntimeProcessLookupPolicy
{
    public static function allows(array $operation, array $contextCodes): bool
    {
        if (($operation['allowProcess'] ?? false) === true ||
            !in_array($operation['type'] ?? null, ['list', 'custom'], true) ||
            !in_array($operation['method'] ?? null, ['GET', 'POST'], true)) {
            return false;
        }
        $action = $operation['bodyTemplate']['operation'] ?? null;
        if (($operation['method'] === 'POST' || $action !== null) && !in_array($action, ['read', 'options'], true)) {
            return false;
        }
        $lookupContext = $operation['requestContextFields'] ?? [];
        if (!is_array($lookupContext) || !array_is_list($lookupContext) || count(array_unique($lookupContext, SORT_REGULAR)) !== count($lookupContext)) {
            return false;
        }
        sort($lookupContext);
        sort($contextCodes);
        return $lookupContext === $contextCodes;
    }
}
