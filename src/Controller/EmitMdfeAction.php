<?php
namespace ControleOnline\Controller;
use ControleOnline\Service\EmitMdfeService;
use Symfony\Component\HttpFoundation\{JsonResponse,Request};
final class EmitMdfeAction { public function __construct(private EmitMdfeService $service){} public function __invoke(Request $r):JsonResponse{$p=json_decode((string)$r->getContent(),true)?:[];try{$m=$this->service->create($p['invoiceTaxIds']??$p['selected']??[],(int)($p['vehicleId']??0),(int)($p['driverId']??0),!empty($p['insurerId'])?(int)$p['insurerId']:null);return new JsonResponse(['id'=>$m->getId(),'@id'=>'/mdfe/'.$m->getId(),'status'=>$m->getStatus()],201);}catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){return new JsonResponse(['error'=>$e->getMessage()],$e->getStatusCode());}catch(\Throwable){return new JsonResponse(['error'=>'Falha ao criar MDF-e.'],500);}}}
