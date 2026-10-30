<?php
namespace App\Attachments;

/** Closed authenticated file protocol. Filesystem roots and key are server configuration. */
final class AttachmentStorageBroker
{
    private $root;private $private;private $secret;
    public function __construct(string $root,string $private,string $secret)
    {
        $r=realpath($root);$p=realpath($private);
        if (!$r || !$p || is_link($root) || is_link($private) || !is_dir($r) || !is_dir($p) || strlen($secret)<32) { throw new \RuntimeException('Storage configuration invalid'); }
        $this->root=str_replace('\\','/',$r);$this->private=str_replace('\\','/',$p);$this->secret=$secret;
        if ($this->private===$this->root || strpos($this->private,$this->root.'/')===0) { throw new \RuntimeException('Storage journal must be private'); }
        foreach (['nonces','journal','stage'] as $name) { $dir=$this->private.'/'.$name;if (is_link($dir) || (!is_dir($dir) && !mkdir($dir,0700))) { throw new \RuntimeException('Storage private directory unavailable'); } }
    }

    public function execute(string $encoded,string $signature,$input):array
    {
        $e=$this->authenticate($encoded,$signature);
        if ($e['op']==='health') { return ['success'=>true,'maxBytes'=>AttachmentFilePolicy::MAX_BYTES,'immutable'=>true]; }
        if ($e['op']==='get') { return $this->download($e['reference']); }
        $lockPath=$this->private.'/journal/'.$e['requestId'].'.lock';
        if (is_link($lockPath)) { throw new \RuntimeException('Storage lock invalid'); }
        $lock=fopen($lockPath,'c+b');if (!$lock || !flock($lock,LOCK_EX)) { throw new \RuntimeException('Storage operation busy'); }
        try {
            $path=$this->private.'/journal/'.$e['requestId'].'.json';
            if (is_link($path)) { throw new \RuntimeException('Storage journal invalid'); }
            $journal=null;
            if (is_file($path)) { $journal=json_decode(file_get_contents($path),true);if (json_last_error()!==JSON_ERROR_NONE || !is_array($journal)) { throw new \RuntimeException('Storage journal corrupted; inspect before retry'); } }
            if ($journal!==null && (!is_array($journal) || ($journal['owner']??null)!==$e['owner'])) { throw new \RuntimeException('Storage owner mismatch'); }
            if ($e['op']==='put') { return $this->put($e,$input,$path,$journal); }
            if (!is_array($journal) || !in_array($journal['state']??null,['staged','committed'],true)) { throw new \RuntimeException('Storage object unavailable'); }
            if ($e['op']==='commit') { $journal['state']='committed';$this->save($path,$journal);return ['success'=>true,'reference'=>$journal['reference'],'state'=>'committed']; }
            if ($journal['state']==='committed') { throw new \RuntimeException('Storage committed object cannot be aborted'); }
            $file=$this->download($journal['reference']);
            if ($file['sha256']!==$journal['sha256'] || $file['size']!==$journal['size'] || strpos($journal['reference'],'ciwatt_'.$e['requestId'].'_')!==0) { throw new \RuntimeException('Storage compensation ownership invalid'); }
            if (!unlink($file['path'])) { throw new \RuntimeException('Storage compensation failed'); }
            $journal['state']='aborted';$this->save($path,$journal);return ['success'=>true,'state'=>'aborted'];
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }

    private function authenticate(string $encoded,string $signature):array
    {
        if (strlen($encoded)>6000 || !preg_match('/\A[a-f0-9]{64}\z/D',$signature) || !hash_equals(hash_hmac('sha256',$encoded,$this->secret),$signature)) { throw new \RuntimeException('Storage authentication invalid'); }
        $raw=base64_decode($encoded,true);$e=$raw===false?null:json_decode($raw,true);
        if (!is_array($e) || ($e['v']??null)!==1 || !in_array($e['op']??null,['health','put','get','commit','abort'],true)) { throw new \RuntimeException('Storage protocol invalid'); }
        $required=['v','op','requestId','timestamp','nonce','owner'];
        if ($e['op']==='put') { $required=array_merge($required,['fileName','size','sha256']); }
        if ($e['op']==='get') { $required[]='reference'; }
        if (array_diff(array_keys($e),$required) || array_diff($required,array_keys($e)) || !is_int($e['timestamp']) || abs(time()-$e['timestamp'])>90) { throw new \RuntimeException('Storage envelope invalid or expired'); }
        foreach (['nonce'=>32,'requestId'=>32,'owner'=>64] as $key=>$length) { if (!is_string($e[$key]) || !preg_match('/\A[a-f0-9]{'.$length.'}\z/D',$e[$key])) { throw new \RuntimeException('Storage identity invalid'); } }
        $nonce=@fopen($this->private.'/nonces/'.$e['nonce'],'x+b');if (!$nonce) { throw new \RuntimeException('Storage replay rejected'); }fwrite($nonce,(string)time());fclose($nonce);
        return $e;
    }

    private function put(array $e,$input,string $path,?array $journal):array
    {
        if (!is_int($e['size']) || $e['size']<1 || $e['size']>AttachmentFilePolicy::MAX_BYTES) { throw new \RuntimeException('Attachment size outside policy'); }
        if (!is_string($e['fileName']) || !is_string($e['sha256']) || !preg_match('/\A[a-f0-9]{64}\z/D',$e['sha256']) || !is_resource($input)) { throw new \RuntimeException('Storage upload invalid'); }
        AttachmentFilePolicy::assertName($e['fileName']);
        if ($journal!==null && (($journal['sha256']??null)!==$e['sha256'] || ($journal['size']??null)!==$e['size'] || ($journal['originalName']??null)!==$e['fileName'] || ($journal['state']??null)==='aborted')) { throw new \RuntimeException('Storage operation cannot be reused with other content'); }
        $extension=strtolower(pathinfo($e['fileName'],PATHINFO_EXTENSION));
        $slug=substr(preg_replace('/[^A-Za-z0-9_-]/','_',pathinfo($e['fileName'],PATHINFO_FILENAME)),0,60);if ($slug==='') { $slug='arquivo'; }
        $reference='ciwatt_'.$e['requestId'].'_'.$slug.'.'.$extension;
        if ($journal===null) { $journal=['owner'=>$e['owner'],'reference'=>$reference,'sha256'=>$e['sha256'],'size'=>$e['size'],'originalName'=>$e['fileName'],'state'=>'receiving'];$this->save($path,$journal); }
        $part=$this->private.'/stage/'.$e['requestId'].'-'.$e['nonce'].'.part';$out=fopen($part,'x+b');if (!$out) { throw new \RuntimeException('Storage stage unavailable'); }
        try {
            $size=0;$hash=hash_init('sha256');
            while (!feof($input)) {
                $chunk=fread($input,65536);if ($chunk===false || $chunk===''&&!feof($input)) { throw new \RuntimeException('Storage incomplete upload'); }
                $size+=strlen($chunk);if ($size>$e['size']) { throw new \RuntimeException('Storage size mismatch'); }hash_update($hash,$chunk);
                $written=0;while ($written<strlen($chunk)) { $n=fwrite($out,substr($chunk,$written));if (!$n) { throw new \RuntimeException('Storage write failed'); }$written+=$n; }
            }
            if ($size!==$e['size'] || !hash_equals($e['sha256'],hash_final($hash))) { throw new \RuntimeException('Storage incomplete upload or hash mismatch'); }
            if (!fflush($out)) { throw new \RuntimeException('Storage flush failed'); }if (function_exists('fsync') && !fsync($out)) { throw new \RuntimeException('Storage sync failed'); }
            fclose($out);$out=null;
            $info=AttachmentFilePolicy::inspect($part,$e['fileName']);
            $target=$this->root.'/'.$reference;
            if (file_exists($target) || is_link($target)) {
                $old=$this->download($reference);if ($old['sha256']!==$info['sha256'] || $old['size']!==$info['size']) { throw new \RuntimeException('Storage immutable object collision'); }
            } else {
                // Exclusive hard link: atomic reservation, never replace an existing document.
                if (!link($part,$target)) { throw new \RuntimeException('Storage exclusive publish failed'); }
                chmod($target,0640);
            }
            $journal=array_replace($journal,$info);if ($journal['state']!=='committed') { $journal['state']='staged'; }$this->save($path,$journal);
            return ['success'=>true,'reference'=>$reference,'sha256'=>$info['sha256'],'size'=>$info['size'],'contentType'=>$info['contentType'],'originalName'=>$info['originalName'],'state'=>$journal['state']];
        } finally { if (is_resource($out)) { fclose($out); }if (is_file($part) && !is_link($part)) { unlink($part); } }
    }

    private function download(string $reference):array
    {
        // Existing names predate the upload policy. Confinement still applies,
        // but do not reject an old extension, leading dot or historical size.
        if ($reference==='' || strlen($reference)>255 || in_array($reference,['.','..'],true) || preg_match('//u',$reference)!==1 || preg_match('/[\\\\\/\x00-\x1f\x7f]/',$reference)) { throw new \RuntimeException('Storage reference invalid'); }
        $path=$this->root.'/'.$reference;$real=realpath($path);
        if (!$real || is_link($path) || !is_file($real) || str_replace('\\','/',dirname($real))!==$this->root || !is_readable($real)) { throw new \RuntimeException('Storage reference unavailable'); }
        $size=filesize($real);$hash=hash_file('sha256',$real);if (!is_int($size) || !is_string($hash)) { throw new \RuntimeException('Storage reference unreadable'); }
        return ['success'=>true,'path'=>$real,'fileName'=>$reference,'size'=>$size,'sha256'=>$hash,'contentType'=>'application/octet-stream'];
    }

    private function save(string $path,array $journal):void
    {
        $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';$stream=fopen($temp,'x+b');if (!$stream) { throw new \RuntimeException('Storage journal unavailable'); }
        try { $bytes=json_encode($journal,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if ($bytes===false || fwrite($stream,$bytes)!==strlen($bytes) || !fflush($stream)) { throw new \RuntimeException('Storage journal write failed'); }if (function_exists('fsync') && !fsync($stream)) { throw new \RuntimeException('Storage journal sync failed'); } } finally { fclose($stream); }
        if (!rename($temp,$path)) { unlink($temp);throw new \RuntimeException('Storage journal publish failed'); }
    }
}
