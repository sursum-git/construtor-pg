<?php
namespace App\Auth;
interface ImaUserWebClientInterface
{
    public function authenticate(string $username, string $password): string;
}
