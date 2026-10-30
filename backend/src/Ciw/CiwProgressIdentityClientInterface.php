<?php
namespace App\Ciw;
use App\Entity\RuntimeUserSession;
interface CiwProgressIdentityClientInterface {public function assertActive(RuntimeUserSession $session):void;}
