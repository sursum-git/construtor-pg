<?php
namespace App\Attachments;

/** Secrets are loaded only when storage is used, from a private server file. */
class AttachmentStorageConfiguration
{
    public function __construct(private readonly ?string $configurationPath=null) {}
    public function settings():array
    {
        $path=$this->configurationPath ?? ($_ENV['CIW_ATTACHMENT_STORAGE_CONFIG'] ?? getenv('CIW_ATTACHMENT_STORAGE_CONFIG'));
        if (!is_string($path) || !is_file($path) || is_link($path)) { throw new \RuntimeException('Attachment storage is not configured'); }
        $c=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($c) || array_diff(array_keys($c),['url','secretFile','journalDir','caFile']) || array_diff(['url','secretFile','journalDir'],array_keys($c))) { throw new \RuntimeException('Attachment configuration invalid'); }
        $u=parse_url($c['url']);
        if (!is_array($u) || ($u['scheme']??null)!=='https' || empty($u['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])) { throw new \RuntimeException('Attachment storage requires a fixed HTTPS endpoint'); }
        if (!is_file($c['secretFile']) || is_link($c['secretFile']) || !is_dir($c['journalDir']) || is_link($c['journalDir'])) { throw new \RuntimeException('Attachment private storage unavailable'); }
        $secret=file_get_contents($c['secretFile']);if (strlen($secret)<32) { throw new \RuntimeException('Attachment storage key invalid'); }
        $ca=$c['caFile']??null;
        if ($ca!==null && (!is_string($ca) || !is_file($ca) || is_link($ca))) { throw new \RuntimeException('Attachment CA bundle unavailable'); }
        return ['url'=>$c['url'],'secret'=>$secret,'journalDir'=>realpath($c['journalDir']),'caFile'=>$ca===null?null:realpath($ca)];
    }
    public function journalDirectory():string { return $this->settings()['journalDir']; }
}
