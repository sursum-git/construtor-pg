<?php
namespace App\Runtime;
use App\Attachments\AttachmentFilePolicy;
use App\Attachments\AttachmentStorageClient;
use App\Attachments\AttachmentStorageConfiguration;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Disk and Progress do not share a transaction. Never infer rollback from a timeout. */
class RuntimeAttachmentWriteCoordinator
{
    public function __construct(private readonly AttachmentStorageClient $storage,private readonly AttachmentStorageConfiguration $configuration) {}
    public function write(UploadedFile $file,string $token,string $actor,array $scope,callable $writer):array
    {
        if (!preg_match('/\Alocal-file:[a-f0-9]{32}\z/D',$token) || $actor==='' || !$file->isValid()) { throw new RuntimeHttpException('ATTACHMENT_UPLOAD_INVALID','Selecione novamente o arquivo.',422); }
        try { $info=AttachmentFilePolicy::inspect($file->getPathname(),$file->getClientOriginalName()); }
        catch (\App\Attachments\AttachmentPolicyViolation $e) { throw new RuntimeHttpException('ATTACHMENT_POLICY_REJECTED','Arquivo recusado. Envie PDF, imagem, Office ou ZIP valido de ate 20 MB.',422); }
        $owner=hash('sha256',json_encode([$actor,$scope],JSON_THROW_ON_ERROR));$id=substr(hash('sha256',$actor.':'.$token),0,32);
        $dir=$this->configuration->journalDirectory();$path=$dir.'/'.$id.'.json';$lockPath=$dir.'/'.$id.'.lock';
        if (is_link($path)||is_link($lockPath)) { throw new \RuntimeException('Attachment operation receipt invalid'); }
        $lock=fopen($lockPath,'c+b');if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { if(is_resource($lock)){fclose($lock);}throw new RuntimeHttpException('ATTACHMENT_OPERATION_BUSY','Envio em andamento. Aguarde e consulte os dados.',409); }
        $receipt=['requestId'=>$id,'owner'=>$owner,'actor'=>$actor,'scope'=>$scope,'file'=>$info,'state'=>'upload_inflight'];
        try {
            if (file_exists($path)) { throw new RuntimeHttpException('ATTACHMENT_OPERATION_REPEATED','Este envio ja foi iniciado. Consulte o registro e o recibo antes de reenviar.',409,['requestId'=>$id,'reconciliationRequired'=>true]); }
            $this->save($path,$receipt);
            try { $stored=$this->storage->put($id,$owner,$file->getPathname(),$info); }
            catch (\Throwable $e) { $receipt['state']='storage_uncertain';$this->save($path,$receipt);throw new RuntimeHttpException('ATTACHMENT_STORAGE_UNCERTAIN','Nao foi possivel confirmar o envio. Nao repita a gravacao; confira o recibo.',502,['requestId'=>$id,'reconciliationRequired'=>true]); }
            $receipt['reference']=$stored['reference'];$receipt['state']='progress_inflight';$this->save($path,$receipt);
            try { $result=$writer($stored['reference']); }
            catch (\Throwable $e) {
                $rollback=$e instanceof RuntimeHttpException && ($e->getDetails()['progressCommitted']??null)===false;
                $receipt['state']=$rollback?'progress_rolled_back':'progress_uncertain';$this->save($path,$receipt);
                if ($rollback) { try{$this->storage->abort($id,$owner);$receipt['state']='aborted';$this->save($path,$receipt);}catch(\Throwable $ignored){/* receipt remains for compensation, never erase an uncertain object */}throw $e; }
                throw new RuntimeHttpException('ATTACHMENT_COMMIT_UNCERTAIN','Nao foi possivel confirmar a gravacao. Recarregue os dados e consulte o recibo antes de reenviar.',502,['requestId'=>$id,'reconciliationRequired'=>true]);
            }
            $receipt['state']='committed';$receipt['result']=$result;$this->save($path,$receipt);
            try { $this->storage->commit($id,$owner);$receipt['storageCommitted']=true;$this->save($path,$receipt); }
            catch (\Throwable $ignored) { /* Progress is committed. Keep the object and receipt; never repeat the writer. */ }
            return $result;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    private function save(string $path,array $receipt):void
    {
        $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';$h=fopen($temp,'x+b');if(!$h){throw new \RuntimeException('Attachment receipt unavailable');}
        try{$raw=json_encode($receipt,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);if(fwrite($h,$raw)!==strlen($raw)||!fflush($h)){throw new \RuntimeException('Attachment receipt write failed');}if(function_exists('fsync')&&!fsync($h)){throw new \RuntimeException('Attachment receipt sync failed');}}finally{fclose($h);}
        if(!rename($temp,$path)){unlink($temp);throw new \RuntimeException('Attachment receipt publish failed');}
    }
}
