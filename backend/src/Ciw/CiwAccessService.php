<?php
namespace App\Ciw;
use App\Auth\{AuthService,AuthenticatedSessionResolver};
use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpFoundation\Request;
class CiwAccessService
{
    public function __construct(private readonly AuthService $auth,private readonly AuthenticatedSessionResolver $sessions,private readonly CiwSubjectResolver $subjects,private readonly CiwProgressIdentityClientInterface $identity,private readonly CiwSqlGrantReaderInterface $grants,private readonly CiwColoracoesPolicy $policy){}
    public function authorize(Request $request,array $payload):array
    {
        if(count($payload)!==3||array_diff(array_keys($payload),['operation','resource','action'])||($payload['operation']??null)!=='authorize'||($payload['resource']??null)!=='cadastros.coloracoes'||!is_string($payload['action']??null))throw new RuntimeHttpException('CIW_ACCESS_INVALID_REQUEST','Consulta de permissao invalida.',400);
        $app=$this->policy->applicationFor($payload['action']);
        $this->auth->currentSession($request);$session=$this->sessions->resolve($request);
        if(!$session||$session->getStatus()!=='active')throw new RuntimeHttpException('AUTH_REQUIRED','Autenticacao obrigatoria.',401);
        $subject=$this->subjects->resolve($session);$this->identity->assertActive($session);$grants=$this->grants->read($subject,$app);
        if(!$grants['active']||!$this->policy->allows($payload['action'],$grants['grants']))throw new RuntimeHttpException('CIW_ACCESS_DENIED','Acesso CIW nao permitido.',403);
        return ['success'=>true,'data'=>[['resource'=>'cadastros.coloracoes','action'=>$payload['action'],'allowed'=>true,'scope'=>'legacy_ciw']]];
    }
}
