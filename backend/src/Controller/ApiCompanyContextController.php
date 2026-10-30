<?php
namespace App\Controller;

use App\Runtime\{ApiCompanyContextResolver,RuntimeHttpException};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;

class ApiCompanyContextController extends AbstractController
{
    public function __construct(private readonly ApiCompanyContextResolver $companies) {}
    #[Route('/api/runtime/api-companies/{moduleId}',name:'runtime_api_company_options',methods:['GET'])]
    public function options(string $moduleId):JsonResponse
    {
        try{return $this->json($this->companies->options($moduleId));}
        catch(RuntimeHttpException $error){return $this->error($error);}
    }
    #[Route('/api/runtime/api-companies/{moduleId}',name:'runtime_api_company_select',methods:['POST'])]
    public function select(string $moduleId,Request $request):JsonResponse
    {
        try{
            $payload=$request->toArray();
            if(array_diff(array_keys($payload),['key','expectedSelectionRevision'])||!is_string($payload['key']??null)||!is_int($payload['expectedSelectionRevision']??null))
                throw new RuntimeHttpException('API_COMPANY_SELECTION_INVALID','Informe a empresa e a revisao atual da selecao.',422);
            return $this->json($this->companies->select($moduleId,$payload['key'],$payload['expectedSelectionRevision']));
        }catch(RuntimeHttpException $error){return $this->error($error);}
    }
    private function error(RuntimeHttpException $error):JsonResponse
    {return $this->json(['error'=>['code'=>$error->getErrorCode(),'message'=>$error->getMessage()]],$error->getStatusCode());}
}
