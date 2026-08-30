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

    #[ORM\JoinColumn(name: 'cte_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: InvoiceTax::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $cte;

    #[ORM\JoinColumn(name: 'invoice_task_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: InvoiceTask::class)]
    #[Groups(['invoice_tax:read'])]
    private $invoiceTask;

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

    public function setInvoiceTask(?InvoiceTask $invoiceTask): self
    {
        $this->invoiceTask = $invoiceTask;
        return $this;
    }

    public function getInvoiceTask(): ?InvoiceTask
    {
        return $this->invoiceTask;
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
