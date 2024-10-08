<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ControleOnline\Controller\DownloadOrderNFAction;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
/**
 * InvoiceTax
 *
 * @ORM\EntityListeners ({ControleOnline\Listener\LogListener::class})
 * @ORM\Table (name="invoice_tax")
 * @ORM\Entity
 */
#[ApiResource(operations: [new Get(security: 'is_granted(\'ROLE_CLIENT\')'), new Get(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')', uriTemplate: '/invoice_taxes/{id}/download-nf', requirements: ['id' => '[\\w-]+'], controller: DownloadOrderNFAction::class), new Post(uriTemplate: '/invoice_taxes/upload-nf', controller: \App\Controller\UploadOrderNFAction::class, deserialize: false, security: 'is_granted(\'ROLE_CLIENT\')', validationContext: ['groups' => ['Default', 'order_upload_nf']], openapiContext: ['consumes' => ['multipart/form-data']])], formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']], normalizationContext: ['groups' => ['invoice_tax:read']], denormalizationContext: ['groups' => ['invoice_tax:write']])]
class InvoiceTax
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
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="ControleOnline\Entity\OrderInvoiceTax", mappedBy="invoiceTax")
     */
    private $order;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice", type="string",  nullable=false)
     */
    private $invoice;
    /**
     * @var \Doctrine\Common\Collections\Collection
     *
     * @ORM\OneToMany(targetEntity="ControleOnline\Entity\ServiceInvoiceTax", mappedBy="service_invoice_tax")
     */
    private $service_invoice_tax;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice_key", type="string",  nullable=true)
     * @Groups({"order:read"})
     */
    private $invoiceKey;
    /**
     * @var string
     *
     * @ORM\Column(name="invoice_number", type="integer",  nullable=false)
     * @Groups({"order:read"})
     */
    private $invoiceNumber;
    public function __construct()
    {
        $this->order = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Add OrderInvoice
     *
     * @param \ControleOnline\Entity\OrderInvoice $order
     * @return People
     */
    public function addOrder(\ControleOnline\Entity\OrderInvoice $order)
    {
        $this->order[] = $order;
        return $this;
    }
    /**
     * Remove OrderInvoice
     *
     * @param \ControleOnline\Entity\OrderInvoice $order
     */
    public function removeOrder(\ControleOnline\Entity\OrderInvoice $order)
    {
        $this->order->removeElement($order);
    }
    /**
     * Get OrderInvoice
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getOrder()
    {
        return $this->order;
    }
    /**
     * Set invoice
     *
     * @param string $invoice
     * @return Order
     */
    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }
    /**
     * Get invoice
     *
     * @return string
     */
    public function getInvoice()
    {
        return $this->invoice;
    }
    /**
     * Set invoiceKey
     *
     * @param string $invoice_number
     * @return InvoiceTax
     */
    public function setInvoiceKey($invoice_key)
    {
        $this->invoiceKey = $invoice_key;
        return $this;
    }
    /**
     * Get invoiceNumber
     *
     * @return string
     */
    public function getInvoiceKey()
    {
        return $this->invoiceKey;
    }
    /**
     * Set invoiceNumber
     *
     * @param integer $invoice_number
     * @return InvoiceTax
     */
    public function setInvoiceNumber($invoice_number)
    {
        $this->invoiceNumber = $invoice_number;
        return $this;
    }
    /**
     * Get invoiceNumber
     *
     * @return integer
     */
    public function getInvoiceNumber()
    {
        return $this->invoiceNumber;
    }
    /**
     * Add ServiceInvoiceTax
     *
     * @param \ControleOnline\Entity\ServiceInvoiceTax $service_invoice_tax
     * @return InvoiceTax
     */
    public function addServiceInvoiceTax(\ControleOnline\Entity\ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax[] = $service_invoice_tax;
        return $this;
    }
    /**
     * Remove ServiceInvoiceTax
     *
     * @param \ControleOnline\Entity\ServiceInvoiceTax $service_invoice_tax
     */
    public function removeServiceInvoiceTax(\ControleOnline\Entity\ServiceInvoiceTax $service_invoice_tax)
    {
        $this->service_invoice_tax->removeElement($service_invoice_tax);
    }
    /**
     * Get ServiceInvoiceTax
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getServiceInvoiceTax()
    {
        return $this->service_invoice_tax;
    }
}
