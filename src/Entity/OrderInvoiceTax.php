<?php

namespace ControleOnline\Entity;

use ControleOnline\Listener\LogListener;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * OrderInvoiceTax
 */
#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')')], formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']], normalizationContext: ['groups' => ['order_invoice_tax:read']], denormalizationContext: ['groups' => ['order_invoice_tax:write']])]
#[ORM\Table(name: 'order_invoice_tax')]
#[ORM\Index(name: 'invoice_tax_id', columns: ['invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'order_id', columns: ['order_id', 'invoice_tax_id'])]
#[ORM\UniqueConstraint(name: 'invoice_type', columns: ['issuer_id', 'invoice_type', 'order_id'])]
#[ORM\EntityListeners([LogListener::class])]
#[ORM\Entity]
class OrderInvoiceTax
{
    /**
     * @var integer
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;
    /**
     * @var \ControleOnline\Entity\InvoiceTax
     *
     * @Groups({"order:read"})
     */
    #[ORM\JoinColumn(name: 'invoice_tax_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\InvoiceTax::class, inversedBy: 'order')]
    private $invoiceTax;
    /**
     * @var \ControleOnline\Entity\Order
     */
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\Order::class, inversedBy: 'invoiceTax')]
    private $order;
    /**
     * @var \ControleOnline\Entity\People
     */
    #[ORM\JoinColumn(name: 'issuer_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\People::class)]
    private $issuer;
    /**
     * @var string
     *
     * @Groups({"order_detail_status:read"})
     */
    #[ORM\Column(name: 'invoice_type', type: 'integer', nullable: false)]
    private $invoiceType;
    public function __construct()
    {
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
        $this->invoiceTax = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set invoiceTax
     *
     * @param \ControleOnline\Entity\InvoiceTax $invoice_tax
     * @return OrderInvoiceTax
     */
    public function setInvoiceTax(\ControleOnline\Entity\InvoiceTax $invoice_tax = null)
    {
        $this->invoiceTax = $invoice_tax;
        return $this;
    }
    /**
     * Get invoiceTax
     *
     * @return \ControleOnline\Entity\InvoiceTax
     */
    public function getInvoiceTax()
    {
        return $this->invoiceTax;
    }
    /**
     * Set order
     *
     * @param \ControleOnline\Entity\Order $order
     * @return OrderInvoiceTax
     */
    public function setOrder(\ControleOnline\Entity\Order $order = null)
    {
        $this->order = $order;
        return $this;
    }
    /**
     * Get order
     *
     * @return \ControleOnline\Entity\Order
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set invoice_type
     *
     * @param integer $invoice_type
     * @return Order
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
