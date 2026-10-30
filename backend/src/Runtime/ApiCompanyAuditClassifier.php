<?php
namespace App\Runtime;
use App\Builder\ApiCompanyContextValidator as Validator;
use App\Entity\BuilderEntity;
use App\Repository\{BuilderEntityRepository,RuntimeEndpointRepository};
use Doctrine\ORM\EntityManagerInterface;

/** Metadata-only classifier, deliberately independent from authentication and transaction services. */
class ApiCompanyAuditClassifier {
 public function __construct(private readonly BuilderEntityRepository $entities,private readonly RuntimeEndpointRepository $endpoints,
  private readonly StructuralIntegrityService $integrity,private readonly EntityManagerInterface $em) {}
 public function assertEntity(BuilderEntity $entity):void {
  $this->em->refresh($entity);$this->integrity->assertBuilderEntity($entity);
 }
 public function pending(string $screenId,string $endpointId,string $handler):?array {
  if(!in_array($handler,['entity.api.crud','entity.api.readonly'],true))return null;
  $endpoint=$this->endpoints->findEnabled($screenId,$endpointId);
  if(!$endpoint||$endpoint->getHandler()!==$handler)throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Endpoint API indisponivel.',422);
  $this->em->refresh($endpoint);$this->integrity->assertEndpoint($endpoint);
  if(!$endpoint->isEnabled()||$endpoint->getHandler()!==$handler)throw new RuntimeHttpException('API_COMPANY_CONFIG_INVALID','Endpoint API indisponivel.',422);
  $code=$endpoint->getConfig()['entityCode']??null;
  $entity=is_string($code)?$this->entities->findOneBy(['code'=>$code]):null;
  if(!$entity)throw new RuntimeHttpException('ENTITY_METADATA_NOT_CONFIGURED','Entidade API indisponivel.',422);
  // Verify even if the configuration was removed: a forged empty object is never legacy.
  $this->assertEntity($entity);
  $config=Validator::validateEntityConfig($entity->getMetadata()['apiCompanyContext']??[],$entity->getEntityType());
  if(!$config)return null;
  $source=Validator::key($entity->getMetadata()['apiBinding']['sourceCode']??null);
  return ['version'=>1,'source'=>'authenticated-api-company','moduleId'=>$config['moduleId'],'sourceCode'=>$source,'mode'=>'pending'];
 }
}
