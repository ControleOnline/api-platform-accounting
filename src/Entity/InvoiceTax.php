<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ControleOnline\Controller\DownloadOrderNFAction;
use ControleOnline\Controller\ListInvoicesWithoutCteAction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_HUMAN\')'),
        new GetCollection(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Get(
            security: 'is_granted(\'PUBLIC_ACCESS\')',
            uriTemplate: '/invoice_taxes/{id}/download-nf',
            requirements: ['id' => '[\\w-]+'],
            controller: DownloadOrderNFAction::class
        ),
        new GetCollection(
            security: 'is_granted(\'ROLE_HUMAN\')',
            uriTemplate: '/invoice_taxes/without-cte',
            controller: ListInvoicesWithoutCteAction::class,
            read: false,
            output: false
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

    #[ORM\OneToMany(targetEntity: OrderInvoiceTax::class, mappedBy: 'invoiceTax')]
    private $order;

    #[ORM\Column(name: 'invoice', type: 'string', nullable: false)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $invoice;

    #[ORM\OneToMany(targetEntity: ServiceInvoiceTax::class, mappedBy: 'service_invoice_tax')]
    private $service_invoice_tax;

    #[ORM\Column(name: 'invoice_key', type: 'string', nullable: true)]
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

    #[ORM\JoinColumn(name: 'issuer_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: People::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $issuer;

    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[Groups(['invoice_tax:read', 'invoice_tax:write'])]
    private $address;

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

    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getInvoice()
    {
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
