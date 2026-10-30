<?php
namespace App\Runtime;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/** Request-local transport objects. Neither file paths nor Response objects come from JSON. */
class RuntimeFileTransferContext
{
    private bool $active=false;
    /** @var array<string,UploadedFile> */
    private array $files=[];
    private ?Response $download=null;
    private ?\Closure $downloadDiscard=null;

    public function beginRequest(Request $request):array
    {
        $multipart=str_starts_with(strtolower((string)$request->headers->get('Content-Type','')),'multipart/form-data');
        if ($multipart) {
            $parameters=$request->request->all();
            if (array_keys($parameters)!==['payload'] || !is_string($parameters['payload']) || strlen($parameters['payload'])>65536) {
                throw new RuntimeHttpException('ATTACHMENT_MULTIPART_INVALID','Envio de arquivo incompleto ou parametros invalidos.',422);
            }
            $raw=$parameters['payload'];$files=$request->files->all();
            if (!$files) { throw new RuntimeHttpException('ATTACHMENT_BYTES_REQUIRED','Selecione novamente o arquivo para enviar.',422); }
        } else { $raw=$request->getContent() ?: '{}';$files=[]; }
        try { $payload=json_decode($raw,true,64,JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new RuntimeHttpException('RUNTIME_PAYLOAD_INVALID','JSON invalido.',422); }
        if (!is_array($payload) || !str_starts_with(ltrim($raw),'{')) { throw new RuntimeHttpException('RUNTIME_PAYLOAD_INVALID','O corpo deve ser um objeto JSON.',422); }
        $this->begin($files);return $payload;
    }

    public function begin(array $files):void
    {
        if ($this->active || count($files)>1) { throw new RuntimeHttpException('ATTACHMENT_TRANSPORT_INVALID','Transporte de arquivo invalido.',422); }
        foreach ($files as $field=>$file) {
            if (!is_string($field) || !preg_match('/\A[a-z][a-z0-9_]{0,79}\z/D',$field) || !$file instanceof UploadedFile || !$file->isValid()) { throw new RuntimeHttpException('ATTACHMENT_UPLOAD_INVALID','Envio de arquivo incompleto ou invalido.',422); }
        }
        $this->files=$files;$this->active=true;
    }

    public function assertEndpoint(string $handler,array $config,array $payload):void
    {
        if (!$this->hasFiles()) { return; }
        $record=$payload['record']??$payload;
        if ($handler!=='entity.api.crud' || !RuntimePurchaseAttachmentAudit::supports($config) || !in_array($config['operation']??null,['create','update'],true) || ($config['fileFields']??null)!==['arquivo_upload'] || array_keys($this->files)!==['arquivo_upload'] || !is_array($record) || !is_string($record['arquivo_upload']??null) || !preg_match('/\Alocal-file:[a-f0-9]{32}\z/D',$record['arquivo_upload'])) {
            throw new RuntimeHttpException('ATTACHMENT_ENDPOINT_INVALID','Este endpoint nao permite o arquivo enviado.',422);
        }
    }

    public function file(string $field):UploadedFile
    {
        if (!$this->active || !isset($this->files[$field])) { throw new RuntimeHttpException('ATTACHMENT_BYTES_REQUIRED','Selecione novamente o arquivo para enviar.',422); }
        return $this->files[$field];
    }
    public function hasFiles():bool { return $this->active && $this->files!==[]; }
    public function isActive():bool { return $this->active; }
    public function setDownload(Response $response,?callable $discard=null):void
    {
        if (!$this->active || $this->download!==null) { throw new RuntimeHttpException('ATTACHMENT_TRANSPORT_INVALID','Download fora do transporte autorizado.',422); }
        $this->download=$response;
        $this->downloadDiscard=$discard===null?null:\Closure::fromCallable($discard);
    }
    public function takeDownload():?Response { $result=$this->download;$this->download=null;$this->downloadDiscard=null;return $result; }
    public function clear():void { $discard=$this->downloadDiscard;$this->files=[];$this->download=null;$this->downloadDiscard=null;$this->active=false;if($discard!==null){$discard();} }
}
