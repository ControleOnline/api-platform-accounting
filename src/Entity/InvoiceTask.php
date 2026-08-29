<?php

namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ControleOnline\Controller\EmitCteAction;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_HUMAN')"),
        new GetCollection(security: "is_granted('ROLE_HUMAN')"),
        new Post(
            uriTemplate: '/invoice_tasks/emit-cte',
            controller: EmitCteAction::class,
            deserialize: false,
            security: "is_granted('ROLE_HUMAN')"
        ),
    ],
    normalizationContext: ['groups' => ['invoice_task:read']],
    denormalizationContext: ['groups' => ['invoice_task:write']]
)]
#[ORM\Table(name: 'invoice_task')]
#[ORM\Entity]
class InvoiceTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice_task:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'task_type', type: 'string', length: 50)]
    #[Groups(['invoice_task:read', 'invoice_task:write'])]
    private string $taskType = 'cte_emission';

    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'status_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['invoice_task:read'])]
    private ?Status $status = null;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['invoice_task:read'])]
    private ?People $company = null;

    #[ORM\ManyToOne(targetEntity: Address::class)]
    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['invoice_task:read'])]
    private ?Address $address = null;

    #[ORM\Column(name: 'cfop', type: 'string', length: 10, nullable: true)]
    #[Groups(['invoice_task:read', 'invoice_task:write'])]
    private ?string $cfop = null;

    #[ORM\Column(name: 'invoice_total', type: 'decimal', precision: 12, scale: 2, nullable: true)]
    #[Groups(['invoice_task:read'])]
    private ?string $invoiceTotal = null;

    #[ORM\Column(name: 'payload', type: 'text', nullable: true)]
    #[Groups(['invoice_task:read', 'invoice_task:write'])]
    private ?string $payload = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    #[Groups(['invoice_task:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTaskType(): string { return $this->taskType; }
    public function setTaskType(string $taskType): self { $this->taskType = $taskType; return $this; }
    public function getStatus(): ?Status { return $this->status; }
    public function setStatus(?Status $status): self { $this->status = $status; return $this; }
    public function getCompany(): ?People { return $this->company; }
    public function setCompany(?People $company): self { $this->company = $company; return $this; }
    public function getAddress(): ?Address { return $this->address; }
    public function setAddress(?Address $address): self { $this->address = $address; return $this; }
    public function getCfop(): ?string { return $this->cfop; }
    public function setCfop(?string $cfop): self { $this->cfop = $cfop; return $this; }
    public function getInvoiceTotal(): ?string { return $this->invoiceTotal; }
    public function setInvoiceTotal($invoiceTotal): self { $this->invoiceTotal = $invoiceTotal !== null ? (string) $invoiceTotal : null; return $this; }
    public function getPayload(): ?string { return $this->payload; }
    public function setPayload(?string $payload): self { $this->payload = $payload; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
