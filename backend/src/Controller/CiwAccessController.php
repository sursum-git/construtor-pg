<?php
namespace App\Controller;
use App\Ciw\CiwAccessService;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
class CiwAccessController
{
    public function __construct(private readonly CiwAccessService $access){}
    #[Route('/api/ciw/access',name:'ciw_access',methods:['POST'])]
    public function authorize(Request $request):JsonResponse
    {
        try{
            $raw=$request->getContent();if(strlen($raw)>16384||!str_starts_with(ltrim($raw),'{'))throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);
            try{$p=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);}
            if(!is_array($p))throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);
            return new JsonResponse($this->access->authorize($request,$p),200,['Cache-Control'=>'no-store']);
        }catch(RuntimeHttpException $e){return new JsonResponse(['success'=>false,'error'=>['code'=>$e->getErrorCode(),'status'=>$e->getStatusCode(),'message'=>'Consulta CIW recusada ou indisponivel.']],$e->getStatusCode(),['Cache-Control'=>'no-store']);}
        catch(\Throwable){return new JsonResponse(['success'=>false,'error'=>['code'=>'CIW_ACCESS_UNAVAILABLE','status'=>503,'message'=>'Permissoes CIW indisponiveis.']],503,['Cache-Control'=>'no-store']);}
    }
}
