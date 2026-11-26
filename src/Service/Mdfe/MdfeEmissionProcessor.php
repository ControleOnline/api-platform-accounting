<?php

namespace ControleOnline\Service\Mdfe;

use ControleOnline\Entity\Integration;
use ControleOnline\Entity\Mdfe;
use ControleOnline\Entity\People;
use ControleOnline\Service\Cte\CteFiscalConfig;
use ControleOnline\Service\FileService;
use Doctrine\ORM\EntityManagerInterface;

class MdfeEmissionProcessor
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CteFiscalConfig $fiscalConfig,
        private MdfeXmlBuilder $xmlBuilder,
        private MdfeSefazClient $sefazClient,
        private FileService $fileService,
    ) {
    }

    public function processMessengerIntegration(Integration $integration): Mdfe
    {
        $body = json_decode((string) $integration->getBody(), true) ?: [];
        $mdfeId = (int) ($body['mdfeId'] ?? 0);
        $mdfe = $this->manager->getRepository(Mdfe::class)->find($mdfeId);
        if (!$mdfe instanceof Mdfe) {
            throw new \RuntimeException('MDF-e da integração não foi encontrado.');
        }

        $company = $mdfe->getCompany();
        $fiscal = $this->fiscalConfig->load($company instanceof People ? $company : null);
        if (empty($fiscal['certificateBinary']) || empty($fiscal['receita-federal-certificate-password'])) {
            throw new \RuntimeException('Configuração fiscal incompleta: certificado e senha são obrigatórios.');
        }

        $xml = $this->xmlBuilder->build($mdfe, $fiscal);
        $sefaz = $this->sefazClient->signAndSend($xml, $fiscal);
        $authorizedXml = (string) $sefaz['xml'];
        $key = (string) ($sefaz['key'] ?: $this->xmlBuilder->extractKey($authorizedXml) ?: '');
        $number = $this->xmlBuilder->extractNumber($authorizedXml) ?: (int) $fiscal['mdfeNextNumber'];

        $mdfe->setStatus(!empty($sefaz['authorized']) ? 'authorized' : 'sent');
        if (method_exists($mdfe, 'setFiscalNumber')) {
            $mdfe->setFiscalNumber($number);
        }
        if (method_exists($mdfe, 'setFiscalSeries')) {
            $mdfe->setFiscalSeries((string) ($fiscal['receita-federal-mdfe-serie'] ?? '1'));
        }
        if (method_exists($mdfe, 'setFiscalKey') && $key !== '') {
            $mdfe->setFiscalKey($key);
        }

        if ($company instanceof People) {
            $this->fileService->addFile(
                $company,
                $authorizedXml,
                'mdfe',
                sprintf('mdfe-%s.xml', $key !== '' ? $key : $number),
                'application',
                'xml',
                false
            );
            $this->fiscalConfig->incrementMdfeLastNumber($company, $number);
        }

        $this->manager->persist($mdfe);
        $this->manager->flush();

        return $mdfe;
    }
}
