<?php

namespace App\Controller;

use App\Email\EmailAnalyticsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EmailAnalyticsController
{
    #[Route('/email-analytics/summary', methods: ['GET'])]
    public function summary(): JsonResponse { return new JsonResponse(EmailAnalyticsRepository::fromEnvironment()->summary()); }

    #[Route('/email-analytics/records', methods: ['GET'])]
    public function records(Request $request): JsonResponse
    {
        $types = array_filter(explode(',', (string) $request->query->get('types', '')));
        $data = EmailAnalyticsRepository::fromEnvironment()->search(['types'=>$types,'page'=>$request->query->getInt('page',1),'pageSize'=>$request->query->getInt('pageSize',20),'sort'=>$request->query->get('sort','dt_hr_evento')]);
        return new JsonResponse(['data'=>$data,'total'=>count($data)]);
    }
}
