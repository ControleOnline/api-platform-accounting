<?php
namespace ControleOnline\Service;
use ControleOnline\Entity\{Integration,Mdfe};
use Doctrine\ORM\EntityManagerInterface;
final class MdfeEmissionService { public function __construct(private EntityManagerInterface $manager){} public function integrate(Integration $i):Mdfe{$p=json_decode((string)$i->getBody(),true)?:[];$m=$this->manager->getRepository(Mdfe::class)->find((int)($p['mdfeId']??0));if(!$m instanceof Mdfe)throw new \RuntimeException('MDF-e da integração não foi encontrado.');throw new \RuntimeException('XML MDF-e ainda não pode ser transmitido sem configuração fiscal completa.');}}
