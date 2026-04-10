<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')')],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['order_invoice_tax:read']],
    denormalizationContext: ['groups' => ['order_invoice_tax:write']]
)]
#[ORM\Table(name: 'order_invoice_tax')]
#[ORM\Index(name: 'invoice_tax_id', columns: ['invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'order_id', columns: ['order_id', 'invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'invoice_type', columns: ['issuer_id', 'invoice_type', 'order_id'])]

#[ORM\Entity]
class OrderInvoiceTax
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['order_invoice_tax:read'])]
    private $id;

    #[ORM\JoinColumn(name: 'invoice_tax_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: InvoiceTax::class, inversedBy: 'order')]
    #[Groups(['order_invoice_tax:read', 'order:read'])]
    private $invoiceTax;

    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'invoiceTax')]
    private $order;

    #[ORM\JoinColumn(name: 'issuer_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: People::class)]
    private $issuer;

    #[ORM\Column(name: 'invoice_type', type: 'integer', nullable: false)]
    #[Groups(['order_invoice_tax:read', 'order_detail_status:read'])]
    private $invoiceType;

    public function __construct()
    {
        $this->order = new ArrayCollection();
        $this->invoiceTax = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setInvoiceTax(InvoiceTax $invoice_tax = null)
    {
        $this->invoiceTax = $invoice_tax;
        return $this;
    }

    public function getInvoiceTax()
    {
        return $this->invoiceTax;
    }

    public function setOrder(Order $order = null)
    {
        $this->order = $order;
        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setInvoiceType($invoice_type)
    {
        $this->invoiceType = $invoice_type;
        return $this;
    }

    public function getInvoiceType()
    {
        return $this->invoiceType;
    }

    public function setIssuer(People $issuer)
    {
        $this->issuer = $issuer;
        return $this;
    }

    public function getIssuer()
    {
        return $this->issuer;
    }
}
