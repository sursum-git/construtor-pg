<?php
namespace App\Ciw;
interface CiwSqlGrantReaderInterface
{
    /** @return array{active:bool,grants:list<array<string,mixed>>} */
    public function read(string $subject,string $application):array;
}
