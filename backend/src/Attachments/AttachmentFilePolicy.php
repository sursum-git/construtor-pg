<?php
namespace App\Attachments;
require_once __DIR__.'/AttachmentPolicyViolation.php';

/** Shared by the native PHP backend and the storage adapter; no framework required. */
final class AttachmentFilePolicy
{
    public const MAX_BYTES = 20971520;
    private const MIME = [
        'pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','bmp'=>'image/bmp','tif'=>'image/tiff','tiff'=>'image/tiff','webp'=>'image/webp',
        'doc'=>'application/msword','xls'=>'application/vnd.ms-excel','ppt'=>'application/vnd.ms-powerpoint',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt'=>'application/vnd.oasis.opendocument.text','ods'=>'application/vnd.oasis.opendocument.spreadsheet','odp'=>'application/vnd.oasis.opendocument.presentation','zip'=>'application/zip',
    ];

    public static function inspect(string $path,string $originalName,int $maxBytes=self::MAX_BYTES):array
    {
        if ($maxBytes<1 || $maxBytes>self::MAX_BYTES) { throw new \RuntimeException('Attachment size policy invalid'); }
        self::assertName($originalName);
        $extension=strtolower(pathinfo($originalName,PATHINFO_EXTENSION));
        if (!isset(self::MIME[$extension])) { throw new AttachmentPolicyViolation('Attachment extension not permitted'); }
        clearstatcache(true,$path);
        if (is_link($path) || !is_file($path) || !is_readable($path)) { throw new \RuntimeException('Attachment input unavailable'); }
        $size=filesize($path);
        if (!is_int($size)) { throw new \RuntimeException('Attachment size unavailable'); }
        if ($size<1 || $size>$maxBytes) { throw new AttachmentPolicyViolation('Attachment size outside policy'); }
        $stream=fopen($path,'rb');if ($stream===false) { throw new \RuntimeException('Attachment input unavailable'); }
        try { $head=fread($stream,32); } finally { fclose($stream); }
        $valid=false;
        switch ($extension) {
            case 'pdf': $valid=strncmp($head,'%PDF-',5)===0;break;
            case 'png': $valid=strncmp($head,"\x89PNG\r\n\x1a\n",8)===0;break;
            case 'jpg':case 'jpeg':$valid=strncmp($head,"\xff\xd8\xff",3)===0;break;
            case 'gif':$valid=strncmp($head,'GIF87a',6)===0 || strncmp($head,'GIF89a',6)===0;break;
            case 'bmp':$valid=strncmp($head,'BM',2)===0;break;
            case 'tif':case 'tiff':$valid=strncmp($head,"II\x2a\x00",4)===0 || strncmp($head,"MM\x00\x2a",4)===0;break;
            case 'webp':$valid=strncmp($head,'RIFF',4)===0 && substr($head,8,4)==='WEBP';break;
            case 'doc':case 'xls':case 'ppt':$valid=strncmp($head,"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1",8)===0;break;
            case 'zip':$valid=self::zip($path,null);break;
            default:$valid=self::zip($path,$extension);
        }
        if (!$valid) { throw new AttachmentPolicyViolation('Attachment content does not match extension'); }
        $digest=hash_file('sha256',$path);
        if (!is_string($digest)) { throw new \RuntimeException('Attachment hash unavailable'); }
        return ['extension'=>$extension,'contentType'=>self::MIME[$extension],'size'=>$size,'sha256'=>$digest,'originalName'=>$originalName];
    }

    public static function assertName(string $name):void
    {
        if ($name==='' || strlen($name)>255 || preg_match('/[\x00-\x1f\x7f\/\\\\:]/',$name) || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i',$name) || $name[0]==='.' || rtrim($name," .")!==$name || !preg_match('//u',$name)) {
            throw new AttachmentPolicyViolation('Attachment name invalid');
        }
    }

    private static function zip(string $path,?string $office):bool
    {
        if (!class_exists(\ZipArchive::class)) { throw new \RuntimeException('Attachment ZIP inspection unavailable'); }
        $zip=new \ZipArchive();$flags=defined('ZipArchive::RDONLY')?constant('ZipArchive::RDONLY'):0;
        if ($zip->open($path,$flags)!==true) { return false; }
        try {
            if ($zip->numFiles>5000) { return false; }
            if ($office===null) { return true; }
            $parts=['docx'=>'word/document.xml','xlsx'=>'xl/workbook.xml','pptx'=>'ppt/presentation.xml'];
            if (isset($parts[$office])) { return $zip->locateName('[Content_Types].xml')!==false && $zip->locateName($parts[$office])!==false; }
            return $zip->getFromName('mimetype',200)===self::MIME[$office];
        } finally { $zip->close(); }
    }
}
