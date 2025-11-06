<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups; 
use Doctrine\Common\Collections\ArrayCollection;
use ControleOnline\Listener\LogListener;

use Doctrine\ORM\Mapping as ORM;

/**
 * ServiceInvoiceTax
 */
#[ORM\Table(name: 'service_invoice_tax')]
#[ORM\Index(name: 'invoice_tax_id', columns: ['invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'invoice_id', columns: ['invoice_id', 'invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'invoice_type', columns: ['issuer_id', 'invoice_type', 'invoice_id'])]
#[ORM\Entity]

class ServiceInvoiceTax
{

    /**
     * @var integer
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var InvoiceTax
     */
    #[ORM\JoinColumn(name: 'invoice_tax_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: InvoiceTax::class, inversedBy: 'service_invoice_tax')]
    private $service_invoice_tax;

    /**
     * @var Invoice
     */
    #[ORM\JoinColumn(name: 'invoice_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'service_invoice_tax')]
    private $invoice;

    /**
     * @var People
     */
    #[ORM\JoinColumn(name: 'issuer_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: People::class)]
    private $issuer;

    /**
     * @var string
     */
    #[ORM\Column(name: 'invoice_type', type: 'integer', nullable: false)]
    private $invoiceType;

    public function __construct()
    {
        $this->invoice             = new ArrayCollection();
        $this->service_invoice_tax = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set service_invoice_tax
     *
     * @param InvoiceTax $service_invoice_tax
     * @return InvoiceTax
     */
    public function setServiceInvoiceTax(InvoiceTax $service_invoice_tax = null)
    {
        $this->service_invoice_tax = $service_invoice_tax;

        return $this;
    }

    /**
     * Get service_invoice_tax
     *
     * @return InvoiceTax
     */
    public function getServiceInvoiceTax()
    {
        return $this->service_invoice_tax;
    }

    /**
     * Set invoice
     *
     * @param Invoice $invoice
     * @return Invoice
     */
    public function setInvoice(Invoice $invoice = null)
    {
        $this->invoice = $invoice;

        return $this;
    }

    /**
     * Get invoice
     *
     * @return Invoice
     */
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * Set invoice_type
     *
     * @param integer $invoice_type
     * @return Invoice
     */
    public function setInvoiceType($invoice_type)
    {
        $this->invoiceType = $invoice_type;

        return $this;
    }

    /**
     * Get invoice_type
     *
     * @return integer
     */
    public function getInvoiceType()
    {
        return $this->invoiceType;
    }

    /**
     * Set issuer
     *
     * @param People $issuer
     * @return People
     */
    public function setIssuer(People $issuer = null)
    {
        $this->issuer = $issuer;

        return $this;
    }

    /**
     * Get issuer
     *
     * @return People
     */
    public function getIssuer()
    {
        return $this->issuer;
    }
}
