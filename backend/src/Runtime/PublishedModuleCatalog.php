<?php
namespace App\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;

/** Module membership comes from signed published navigation, including linked pages. */
class PublishedModuleCatalog
{
    public function __construct(private readonly ScreenDefinitionRepository $screens, private readonly StructuralIntegrityService $integrity) {}

    public function screen(string $id): ?ScreenDefinition
    {
        $screen=$this->screens->findPublishedByScreenId($id);
        if(!$screen || $screen->getStatus()!=='published')return null;
        $this->integrity->assertScreen($screen);
        return $screen;
    }

    /** @return list<string> */
    public function screenIds(string $moduleId): array
    {
        $home=$this->screen('home');if(!$home)return [];
        $d=$home->getDefinition();$roots=[];$otherRoots=[];$groups=[];
        foreach($d['navigation']['groups']??[] as $g){
            foreach($g['items']??[] as $item)if(is_string($item['programId']??null))$groups[$item['programId']][]=$g['moduleId']??'';
        }
        foreach($d['programs']??[] as $program){
            $id=$program['screenId']??null;if(!is_string($id)||$id==='')continue;
            $modules=isset($program['moduleId'])?[$program['moduleId']]:($groups[$program['id']??'']??[]);
            if(in_array($moduleId,$modules,true))$roots[]=$id;elseif($modules)$otherRoots[$id]=true;
        }
        $found=[];$queue=$roots;
        while($queue){
            $id=array_shift($queue);if(isset($found[$id])||isset($otherRoots[$id])||in_array($id,['home','admin.transacoes','admin.logs-transacoes'],true))continue;
            if(count($found)>=512)throw new RuntimeHttpException('MODULE_CATALOG_TOO_LARGE','Catalogo do modulo excede o limite.',422);
            $screen=$this->screen($id);if(!$screen)continue;
            $found[$id]=true;$this->collectReferences($screen->getDefinition(),$queue);
        }
        $ids=array_keys($found);sort($ids,SORT_STRING);return $ids;
    }

    private function collectReferences(array $node,array &$ids): void
    {
        foreach($node as $key=>$value){
            if($key==='screenId'&&is_string($value)&&$value!=='')$ids[]=$value;
            elseif(is_array($value))$this->collectReferences($value,$ids);
        }
    }
}
