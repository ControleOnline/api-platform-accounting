<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ServiceInvoiceTax
 *
 * @ORM\Table(name="service_invoice_tax", uniqueConstraints={@ORM\UniqueConstraint(name="invoice_id", columns={"invoice_id", "invoice_tax_id"}),@ORM\UniqueConstraint(name="invoice_type", columns={"issuer_id", "invoice_type", "invoice_id"})}, indexes={@ORM\Index(name="invoice_tax_id", columns={"invoice_tax_id"})})
 * @ORM\Entity
 *  @ORM\EntityListeners({ControleOnline\Listener\LogListener::class})
 */
class ServiceInvoiceTax
{

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \ControleOnline\Entity\InvoiceTax
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\InvoiceTax", inversedBy="service_invoice_tax")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="invoice_tax_id", referencedColumnName="id")
     * })
     */
    private $service_invoice_tax;

    /**
     * @var \ControleOnline\Entity\Invoice
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\Invoice", inversedBy="service_invoice_tax")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="invoice_id", referencedColumnName="id")
     * })
     */
    private $invoice;

    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="issuer_id", referencedColumnName="id")
     * })
     */
    private $issuer;

    /**
     * @var string
     *
     * @ORM\Column(name="invoice_type", type="integer",  nullable=false)
     */
    private $invoiceType;

    public function __construct()
    {
        $this->invoice             = new \Doctrine\Common\Collections\ArrayCollection();
        $this->service_invoice_tax = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @param \ControleOnline\Entity\InvoiceTax $service_invoice_tax
     * @return InvoiceTax
     */
    public function setServiceInvoiceTax(\ControleOnline\Entity\InvoiceTax $service_invoice_tax = null)
    {
        $this->service_invoice_tax = $service_invoice_tax;

        return $this;
    }

    /**
     * Get service_invoice_tax
     *
     * @return \ControleOnline\Entity\InvoiceTax
     */
    public function getServiceInvoiceTax()
    {
        return $this->service_invoice_tax;
    }

    /**
     * Set invoice
     *
     * @param \ControleOnline\Entity\Invoice $invoice
     * @return Invoice
     */
    public function setInvoice(\ControleOnline\Entity\Invoice $invoice = null)
    {
        $this->invoice = $invoice;

        return $this;
    }

    /**
     * Get invoice
     *
     * @return \ControleOnline\Entity\Invoice
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
     * @param \ControleOnline\Entity\People $issuer
     * @return People
     */
    public function setIssuer(\ControleOnline\Entity\People $issuer = null)
    {
        $this->issuer = $issuer;

        return $this;
    }

    /**
     * Get issuer
     *
     * @return \ControleOnline\Entity\People
     */
    public function getIssuer()
    {
        return $this->issuer;
    }
}
