<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    #[Route('/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'service' => 'construtor-pg',
        ]);
    }
}
