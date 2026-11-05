<?php

namespace App\Controller;

use App\Email\EmailAnalyticsRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class EmailAnalyticsController
{
    #[Route('/email-analytics/summary', methods: ['GET'])]
    public function summary(Request $request): JsonResponse { return new JsonResponse(EmailAnalyticsRepository::fromEnvironment()->summary(['date' => $request->query->get('date'), 'text' => $request->query->get('text')])); }

    #[Route('/email-analytics/records', methods: ['GET'])]
    public function records(Request $request): JsonResponse
    {
        $types = array_filter(explode(',', (string) $request->query->get('types', '')));
        $data = EmailAnalyticsRepository::fromEnvironment()->search(['types'=>$types,'date'=>$request->query->get('date'),'text'=>$request->query->get('text'),'page'=>$request->query->getInt('page',1),'pageSize'=>$request->query->getInt('pageSize',20),'sort'=>$request->query->get('sort','dt_hr_evento')]);
        $total = (int) ($data[0]['total_records'] ?? 0);
        foreach ($data as &$row) unset($row['total_records']);
        return new JsonResponse(['data'=>$data,'total'=>$total]);
    }
}
