<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ControleOnline\Controller\DownloadOrderNFAction;
use ControleOnline\Controller\InvoiceTaxUploadController;
use ControleOnline\Entity\File;
use ControleOnline\Entity\Status;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_HUMAN\')'),
        new GetCollection(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Post(
            uriTemplate: '/invoice_taxes/upload',
            controller: InvoiceTaxUploadController::class,
            deserialize: false,
            security: 'is_granted(\'ROLE_HUMAN\')'
        ),
        new Get(
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            uriTemplate: '/invoice_taxes/{id}/download-nf',
            requirements: ['id' => '[\\w-]+'],
            controller: DownloadOrderNFAction::class
        ),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['invoice_tax:read']],
    denormalizationContext: ['groups' => ['invoice_tax:write']]
)]
#[ORM\Table(name: 'invoice_tax')]
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

    #[ORM\Column(name: 'invoice', type: 'text', nullable: true)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private ?string $invoice = null;

    #[ORM\OneToMany(targetEntity: ServiceInvoiceTax::class, mappedBy: 'service_invoice_tax')]
    private $service_invoice_tax;

    #[ORM\Column(name: 'invoice_key', type: 'string', nullable: true)]
    #[Groups(['invoice_tax:read', 'order:read'])]
    private $invoiceKey;

    #[ORM\Column(name: 'invoice_number', type: 'integer', nullable: false)]
    #[Groups(['invoice_tax:read', 'order:read'])]
    private $invoiceNumber;

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

    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getInvoice()
    {
        if ($this->file !== null) {
            try {
                return $this->file->getContent(true);
            } catch (\Throwable $e) {
                // fallback to invoice string
            }
        }
        return $this->invoice;
    }

    public function setInvoiceKey($invoice_key)
    {
        $this->invoiceKey = $invoice_key;
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