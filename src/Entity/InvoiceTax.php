<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ControleOnline\Controller\DownloadOrderNFAction;
use ControleOnline\Controller\InvoiceTaxUploadController;
use ControleOnline\Controller\ListInvoicesWithoutCteAction;
use ControleOnline\Controller\EmitCteAction;
use ControleOnline\Entity\Address;
use ControleOnline\Entity\File;
use ControleOnline\Entity\People;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ControleOnline\Entity\Status;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            name: 'invoice_taxes_without_cte',
            uriTemplate: '/invoice_taxes/without-cte',
            controller: ListInvoicesWithoutCteAction::class,
            read: false,
            output: false,
            security: 'is_granted(\'ROLE_HUMAN\')'
        ),
        new GetCollection(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Get(
            security: 'is_granted(\'ROLE_HUMAN\')',
            requirements: ['id' => '\\d+']
        ),
        new Post(
            uriTemplate: '/invoice_taxes/upload',
            controller: InvoiceTaxUploadController::class,
            deserialize: false,
            security: 'is_granted(\'ROLE_HUMAN\')'
        ),
        new Post(
            uriTemplate: '/invoice_tasks/emit-cte',
            controller: EmitCteAction::class,
            deserialize: false,
            security: "is_granted('ROLE_HUMAN')"
        ),
        new Get(
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            uriTemplate: '/invoice_taxes/{id}/download-nf',
            requirements: ['id' => '\\d+'],
            controller: DownloadOrderNFAction::class
        ),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice_tax:read']],
    denormalizationContext: ['groups' => ['invoice_tax:write']]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['invoiceModel' => 'exact', 'invoice_model' => 'exact', 'fiscalType' => 'exact', 'fiscal_type' => 'exact', 'fiscalSeries' => 'exact', 'fiscal_series' => 'exact', 'fiscalNumber' => 'exact', 'fiscal_number' => 'exact', 'fiscalProtocol' => 'exact', 'fiscal_protocol' => 'exact', 'fiscalAuthorizationStatus' => 'exact', 'fiscal_authorization_status' => 'exact', 'status' => 'exact', 'status.realStatus' => 'exact', 'invoiceNumber' => 'exact', 'invoiceKey' => 'exact', 'invoice_key' => 'exact', 'company' => 'exact', 'client' => 'exact', 'provider' => 'exact', 'carrier' => 'exact', 'id' => 'exact'])]
#[ORM\Table(name: 'invoice_tax')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_tax_invoice_key', columns: ['invoice_key'])]
#[ORM\Entity]
class InvoiceTax
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['invoice_tax:read'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?File $file = null;

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'status_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?Status $status = null;

    #[ORM\OneToMany(targetEntity: OrderInvoiceTax::class, mappedBy: 'invoiceTax')]
    private $order;

    #[ORM\OneToMany(targetEntity: ServiceInvoiceTax::class, mappedBy: 'service_invoice_tax')]
    private $service_invoice_tax;

    #[ORM\Column(name: 'invoice_key', type: 'string', length: 44, nullable: true, unique: true)]
    #[Groups(['invoice_tax:read', 'order:read'])]
    private $invoiceKey;

    #[ORM\Column(name: 'invoice_number', type: 'integer', nullable: false)]
    #[Groups(['invoice_tax:read', 'order:read'])]
    private $invoiceNumber;

    #[ORM\Column(name: 'invoice_model', type: 'integer', nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $invoiceModel;

    #[ORM\Column(name: 'invoice_total', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $invoiceTotal;

    #[ORM\Column(name: 'fiscal_type', type: 'string', length: 10, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $fiscalType = null;

    #[ORM\Column(name: 'fiscal_series', type: 'string', length: 20, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $fiscalSeries = null;

    #[ORM\Column(name: 'fiscal_number', type: 'string', length: 30, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $fiscalNumber = null;

    #[ORM\Column(name: 'fiscal_protocol', type: 'string', length: 60, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $fiscalProtocol = null;

    #[ORM\Column(name: 'fiscal_authorization_status', type: 'string', length: 10, nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $fiscalAuthorizationStatus = null;

    #[ORM\JoinColumn(name: 'cte_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: InvoiceTax::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $cte;

    #[ORM\JoinColumn(name: 'invoice_task_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Integration::class)]
    #[Groups(['invoice_tax:read'])]
    private $integration;

    #[ORM\JoinColumn(name: 'issuer_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $issuer;

    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $address;

    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $company;

    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $client;

    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $provider;

    #[ORM\JoinColumn(name: 'carrier_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $carrier;

    #[ORM\JoinColumn(name: 'provider_address_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $providerAddress;

    #[ORM\JoinColumn(name: 'client_address_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $clientAddress;

    #[ORM\JoinColumn(name: 'carrier_address_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $carrierAddress;

    public function __construct()
    {
        $this->order = new ArrayCollection();
        $this->service_invoice_tax = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function addOrder(OrderInvoice $order)
    {
        $this->order[] = $order;
        return $this;
    }

    public function removeOrder(OrderInvoice $order)
    {
        $this->order->removeElement($order);
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): self
    {
        $this->status = $status;
        return $this;
    }

    #[Groups(['invoice_tax:read'])]
    public function getInvoice()
    {
        if ($this->file === null) {
            return null;
        }

        try {
            return $this->file->getContent(true);
        } catch (\Throwable) {
            return null;
        }
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalDocument(): array
    {
        $xml = $this->getInvoice();
        $model = (int) ($this->invoiceModel ?: $this->detectFiscalModel($xml));
        $isCte = $model === 57;
        $type = $this->fiscalType ?: match ($model) {
            57 => 'CTE',
            55, 65 => 'NFE',
            default => null,
        };

        return [
            'type' => $type,
            'model' => $model ?: null,
            'series' => $this->firstFiscalValue($this->fiscalSeries, $this->extractFiscalXmlValue($xml, 'serie')),
            'number' => $this->firstFiscalValue($this->fiscalNumber, $this->extractFiscalXmlValue($xml, $isCte ? 'nCT' : 'nNF')),
            'key' => $this->firstFiscalValue($this->invoiceKey, $this->extractFiscalXmlValue($xml, $isCte ? 'chCTe' : 'chNFe')),
            'issuedAt' => $this->firstFiscalValue($this->extractFiscalXmlValue($xml, 'dhEmi')),
            'cfop' => $this->firstFiscalValue($this->extractFiscalXmlValue($xml, 'CFOP')),
            'protocol' => $this->firstFiscalValue($this->fiscalProtocol, $this->extractFiscalXmlValue($xml, 'nProt')),
            'authorizationStatus' => $this->firstFiscalValue($this->fiscalAuthorizationStatus, $this->extractFiscalXmlValue($xml, 'cStat')),
            'authorizationMessage' => $this->firstFiscalValue($this->extractFiscalXmlValue($xml, 'xMotivo')),
            'authorizedAt' => $this->firstFiscalValue($this->extractFiscalXmlValue($xml, 'dhRecbto')),
            'total' => $this->firstFiscalValue($this->extractFiscalXmlValue($xml, $isCte ? 'vTPrest' : 'vNF')),
        ];
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalType(): ?string
    {
        return $this->fiscalType;
    }

    public function setFiscalType(?string $fiscalType): self
    {
        $this->fiscalType = $this->normalizeFiscalString($fiscalType);

        return $this;
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalSeries(): ?string
    {
        return $this->fiscalSeries;
    }

    public function setFiscalSeries(?string $fiscalSeries): self
    {
        $this->fiscalSeries = $this->normalizeFiscalString($fiscalSeries);

        return $this;
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalNumber(): ?string
    {
        return $this->fiscalNumber;
    }

    public function setFiscalNumber(?string $fiscalNumber): self
    {
        $this->fiscalNumber = $this->normalizeFiscalString($fiscalNumber);

        return $this;
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalProtocol(): ?string
    {
        return $this->fiscalProtocol;
    }

    public function setFiscalProtocol(?string $fiscalProtocol): self
    {
        $this->fiscalProtocol = $this->normalizeFiscalString($fiscalProtocol);

        return $this;
    }

    #[Groups(['invoice_tax:read', 'order:read'])]
    public function getFiscalAuthorizationStatus(): ?string
    {
        return $this->fiscalAuthorizationStatus;
    }

    public function setFiscalAuthorizationStatus(?string $fiscalAuthorizationStatus): self
    {
        $this->fiscalAuthorizationStatus = $this->normalizeFiscalString($fiscalAuthorizationStatus);

        return $this;
    }

    public function syncFiscalDocumentFieldsFromXml(?string $xml = null): self
    {
        $xml ??= $this->getInvoice();
        $model = (int) ($this->invoiceModel ?: $this->detectFiscalModel($xml));
        $isCte = $model === 57;

        $this->setFiscalType(match ($model) {
            57 => 'CTE',
            55, 65 => 'NFE',
            default => null,
        });
        $this->setFiscalSeries($this->extractFiscalXmlValue($xml, 'serie'));
        $this->setFiscalNumber($this->extractFiscalXmlValue($xml, $isCte ? 'nCT' : 'nNF'));
        $this->setFiscalProtocol($this->extractFiscalXmlValue($xml, 'nProt'));
        $this->setFiscalAuthorizationStatus($this->extractFiscalXmlValue($xml, 'cStat'));

        return $this;
    }

    private function normalizeFiscalString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function firstFiscalValue(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function detectFiscalModel(?string $xml): int
    {
        if (is_string($xml) && (stripos($xml, '<CTe') !== false || stripos($xml, '<cteProc') !== false)) {
            return 57;
        }

        if (is_string($xml) && (stripos($xml, '<NFe') !== false || stripos($xml, '<nfeProc') !== false)) {
            return 55;
        }

        return 0;
    }

    private function extractFiscalXmlValue(?string $xml, string $tagName): string
    {
        if (!is_string($xml) || trim($xml) === '') {
            return '';
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return '';
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[local-name()="%s"]', $tagName));
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)->textContent);
    }

    public function setInvoice($invoice)
    {
        return $this;
    }

    public function setInvoiceKey($invoice_key)
    {
        $this->invoiceKey = $invoice_key === null || $invoice_key === '' ? null : (string) $invoice_key;
        return $this;
    }

    public function getInvoiceKey()
    {
        return $this->invoiceKey;
    }

    public function setInvoiceNumber($invoice_number)
    {
        $this->invoiceNumber = $invoice_number;
        return $this;
    }

    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceModel($invoiceModel)
    {
        $this->invoiceModel = $invoiceModel === null ? null : (int) $invoiceModel;
        return $this;
    }

    public function getInvoiceModel()
    {
        return $this->invoiceModel;
    }

    public function setInvoiceTotal($invoiceTotal)
    {
        $this->invoiceTotal = $invoiceTotal;
        return $this;
    }

    public function getInvoiceTotal()
    {
        return $this->invoiceTotal;
    }

    public function setCte(?InvoiceTax $cte)
    {
        $this->cte = $cte;
        return $this;
    }

    public function getCte(): ?InvoiceTax
    {
        return $this->cte;
    }

    public function setIntegration(?Integration $integration): self
    {
        $this->integration = $integration;
        return $this;
    }

    public function getIntegration(): ?Integration
    {
        return $this->integration;
    }

    public function setIssuer(?People $issuer)
    {
        $this->issuer = $issuer;
        return $this;
    }

    public function getIssuer(): ?People
    {
        return $this->issuer;
    }

    public function setAddress(?Address $address)
    {
        $this->address = $address;
        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setCompany(?People $company)
    {
        $this->company = $company;
        return $this;
    }

    public function getCompany(): ?People
    {
        return $this->company;
    }

    public function setClient(?People $client)
    {
        $this->client = $client;
        return $this;
    }

    public function getClient(): ?People
    {
        return $this->client;
    }

    public function setProvider(?People $provider)
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProvider(): ?People
    {
        return $this->provider;
    }

    public function setCarrier(?People $carrier)
    {
        $this->carrier = $carrier;
        return $this;
    }

    public function getCarrier(): ?People
    {
        return $this->carrier;
    }

    public function setProviderAddress(?Address $providerAddress)
    {
        $this->providerAddress = $providerAddress;
        return $this;
    }

    public function getProviderAddress(): ?Address
    {
        return $this->providerAddress;
    }

    public function setClientAddress(?Address $clientAddress)
    {
        $this->clientAddress = $clientAddress;
        return $this;
    }

    public function getClientAddress(): ?Address
    {
        return $this->clientAddress;
    }

    public function setCarrierAddress(?Address $carrierAddress)
    {
        $this->carrierAddress = $carrierAddress;
        return $this;
    }

    public function getCarrierAddress(): ?Address
    {
        return $this->carrierAddress;
    }

    public function addServiceInvoiceTax(ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax[] = $service_invoice_tax;
        return $this;
    }

    public function removeServiceInvoiceTax(ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax->removeElement($service_invoice_tax);
    }

    public function getServiceInvoiceTax()
    {
        return $this->service_invoice_tax;
    }
}
