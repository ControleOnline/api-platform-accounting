<?php
namespace ControleOnline\Entity;
use ApiPlatform\Metadata\{ApiResource,Get,GetCollection,Post};
use ControleOnline\Controller\EmitMdfeAction;
use Doctrine\Common\Collections\{ArrayCollection,Collection};
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'mdfe')]
#[ApiResource(operations: [new Get(security: "is_granted('ROLE_HUMAN')"), new GetCollection(security: "is_granted('ROLE_HUMAN')"), new Post(uriTemplate: '/mdfe/emit', controller: EmitMdfeAction::class, deserialize: false, security: "is_granted('ROLE_HUMAN')")])]
class Mdfe {
 #[ORM\Id,ORM\GeneratedValue,ORM\Column(type:'integer')] private ?int $id=null;
 #[ORM\ManyToOne(targetEntity:People::class),ORM\JoinColumn(name:'company_id',nullable:false,onDelete:'CASCADE')] private ?People $company=null;
 #[ORM\ManyToOne(targetEntity:DeliveryCourierVehicle::class),ORM\JoinColumn(name:'vehicle_id',nullable:false)] private ?DeliveryCourierVehicle $vehicle=null;
 #[ORM\ManyToOne(targetEntity:People::class),ORM\JoinColumn(name:'driver_id',nullable:false)] private ?People $driver=null;
 #[ORM\ManyToOne(targetEntity:People::class),ORM\JoinColumn(name:'insurer_id',nullable:true)] private ?People $insurer=null;
 #[ORM\ManyToMany(targetEntity:InvoiceTax::class),ORM\JoinTable(name:'mdfe_invoice_tax'),ORM\JoinColumn(name:'mdfe_id',referencedColumnName:'id',onDelete:'CASCADE'),ORM\InverseJoinColumn(name:'invoice_tax_id',referencedColumnName:'id',onDelete:'CASCADE')] private Collection $documents;
 #[ORM\Column(type:'string',length:30)] private string $status='draft';
 #[ORM\Column(name:'fiscal_number',type:'integer',nullable:true)] private ?int $fiscalNumber=null;
 #[ORM\Column(name:'fiscal_series',type:'string',length:10,nullable:true)] private ?string $fiscalSeries=null;
 #[ORM\Column(name:'fiscal_key',type:'string',length:44,nullable:true)] private ?string $fiscalKey=null;
 #[ORM\Column(name:'creation_date',type:'datetime_immutable')] private \DateTimeImmutable $creationDate;
 public function __construct(){ $this->documents=new ArrayCollection();$this->creationDate=new \DateTimeImmutable(); }
 public function getId():?int{return $this->id;} public function getCompany():?People{return $this->company;} public function setCompany(?People $v):self{$this->company=$v;return $this;} public function setVehicle(?DeliveryCourierVehicle $v):self{$this->vehicle=$v;return $this;} public function setDriver(?People $v):self{$this->driver=$v;return $this;} public function setInsurer(?People $v):self{$this->insurer=$v;return $this;} public function getDocuments():Collection{return $this->documents;} public function addDocument(InvoiceTax $v):self{$this->documents->add($v);return $this;} public function getStatus():string{return $this->status;} public function setStatus(string $v):self{$this->status=$v;return $this;}
}
