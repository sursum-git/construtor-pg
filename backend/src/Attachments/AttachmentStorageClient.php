<?php
namespace App\Attachments;

/** Stream transport, no redirects and no browser-controlled URL, reference or key. */
class AttachmentStorageClient
{
    public function __construct(private readonly AttachmentStorageConfiguration $configuration) {}
    public function put(string $id,string $owner,string $path,array $info):array
    { return $this->exchange('put',$id,$owner,['fileName'=>$info['originalName'],'size'=>$info['size'],'sha256'=>$info['sha256']],$path); }
    public function commit(string $id,string $owner):array { return $this->exchange('commit',$id,$owner); }
    public function abort(string $id,string $owner):array { return $this->exchange('abort',$id,$owner); }
    public function get(string $reference,string $owner):array { return $this->exchange('get',bin2hex(random_bytes(16)),$owner,['reference'=>$reference]); }
    public function health():array { return $this->exchange('health',bin2hex(random_bytes(16)),hash('sha256','storage-health')); }

    protected function exchange(string $op,string $id,string $owner,array $extra=[],?string $path=null):array
    {
        $c=$this->configuration->settings();
        $e=['v'=>1,'op'=>$op,'requestId'=>$id,'timestamp'=>time(),'nonce'=>bin2hex(random_bytes(16)),'owner'=>$owner]+$extra;
        $encoded=base64_encode(json_encode($e,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $headers=['Content-Type: application/octet-stream','Accept: application/json','X-Ciw-Envelope: '.$encoded,'X-Ciw-Signature: '.hash_hmac('sha256',$encoded,$c['secret']),'Expect:'];
        $ch=curl_init($c['url']);$in=null;$out=null;$temp=null;$responseHeaders=[];$size=0;$limit=$op==='get'?PHP_INT_MAX:65536;
        try {
            if ($op==='get') { $temp=tempnam($c['journalDir'],'download-');if ($temp===false) { throw new \RuntimeException('Attachment download stage unavailable'); }$out=fopen($temp,'w+b'); }
            else { $out=fopen('php://temp/maxmemory:65536','w+b'); }
            if (!$out || !$ch) { throw new \RuntimeException('Attachment transport unavailable'); }
            curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>in_array($op,['put','get'],true)?120:15,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_HEADERFUNCTION=>static function($h,string $line)use(&$responseHeaders,&$limit,$op):int {
                    if (str_starts_with($line,'HTTP/')) { $responseHeaders=[]; }
                    elseif (str_contains($line,':')) { [$name,$value]=explode(':',$line,2);$name=strtolower(trim($name));$responseHeaders[$name]=array_key_exists($name,$responseHeaders)?null:trim($value); }
                    if (trim($line)==='' && $op==='get') {
                        $n=$responseHeaders['content-length']??null;
                        if (!is_string($n) || !preg_match('/\A[0-9]{1,15}\z/D',$n)) { return 0; }
                        $limit=(int)$n; // Historical files may exceed the NEW upload limit.
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION=>static function($h,string $bytes)use($out,&$size,&$limit):int { $size+=strlen($bytes);if($size>$limit){return 0;}return fwrite($out,$bytes); },
            ]);
            if ($c['caFile']??null) { curl_setopt($ch,CURLOPT_CAINFO,$c['caFile']); }
            if ($path!==null) { $in=fopen($path,'rb');if (!$in) { throw new \RuntimeException('Attachment upload unreadable'); }curl_setopt_array($ch,[CURLOPT_UPLOAD=>true,CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_INFILE=>$in,CURLOPT_INFILESIZE=>$extra['size']]); }
            else { curl_setopt($ch,CURLOPT_POSTFIELDS,''); }
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if ($ok===false || $status!==200) { throw new \RuntimeException('Attachment storage communication failed; inspect receipt before retry'); }
            fflush($out);rewind($out);
            if ($op==='get') {
                $hash=$responseHeaders['x-ciw-sha256']??null;
                if (!is_string($hash) || !preg_match('/\A[a-f0-9]{64}\z/D',$hash) || $size!==$limit || !hash_equals($hash,hash_file('sha256',$temp))) { throw new \RuntimeException('Attachment download integrity failed'); }
                fclose($out);$out=null;$result=['path'=>$temp,'size'=>$size,'sha256'=>$hash,'fileName'=>$extra['reference']];$temp=null;return $result;
            }
            $result=json_decode(stream_get_contents($out),true,16,JSON_THROW_ON_ERROR);
            if (!is_array($result) || ($result['success']??null)!==true || array_key_exists('path',$result)) { throw new \RuntimeException('Attachment storage response invalid'); }
            if ($op==='put' && (!is_string($result['reference']??null) || !str_starts_with($result['reference'],'ciwatt_'.$id.'_') || ($result['size']??null)!==$extra['size'] || ($result['sha256']??null)!==$extra['sha256'])) { throw new \RuntimeException('Attachment storage receipt mismatch'); }
            return $result;
        } finally { if(is_resource($in)){fclose($in);}if(is_resource($out)){fclose($out);}if($ch){curl_close($ch);}if($temp!==null && is_file($temp)){unlink($temp);} }
    }
}
