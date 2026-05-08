<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ClienteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[ORM\Entity(repositoryClass: ClienteRepository::class)]
#[ORM\Table(name: 'cliente')]
class Cliente
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120)]
    private string $nome = '';

    #[Assert\Email]
    #[Assert\Length(max: 150)]
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $email = null;

    #[Assert\Length(max: 30)]
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 20)]
    private string $status = 'ATIVO';

    #[ORM\Column(length: 2)]
    private string $tipoPessoa = 'PF';

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $uf = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $cidade = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $razaoSocial = null;

    #[ORM\Column(length: 18, nullable: true)]
    private ?string $cnpj = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dataCadastro = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $valorTotal = '0.00';

    #[ORM\Column(nullable: true)]
    private ?int $qtdePedidos = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observacao = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->dataCadastro = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): string
    {
        return $this->nome;
    }

    public function setNome(string $nome): self
    {
        $this->nome = $nome;
        $this->touch();
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email ?: null;
        $this->touch();
        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(?string $telefone): self
    {
        $this->telefone = $telefone ?: null;
        $this->touch();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();
        return $this;
    }

    public function getTipoPessoa(): string
    {
        return $this->tipoPessoa;
    }

    public function setTipoPessoa(string $tipoPessoa): self
    {
        $this->tipoPessoa = $tipoPessoa;
        $this->touch();
        return $this;
    }

    public function getUf(): ?string
    {
        return $this->uf;
    }

    public function setUf(?string $uf): self
    {
        $this->uf = $uf ?: null;
        $this->touch();
        return $this;
    }

    public function getCidade(): ?string
    {
        return $this->cidade;
    }

    public function setCidade(?string $cidade): self
    {
        $this->cidade = $cidade ?: null;
        $this->touch();
        return $this;
    }

    public function getRazaoSocial(): ?string
    {
        return $this->razaoSocial;
    }

    public function setRazaoSocial(?string $razaoSocial): self
    {
        $this->razaoSocial = $razaoSocial ?: null;
        $this->touch();
        return $this;
    }

    public function getCnpj(): ?string
    {
        return $this->cnpj;
    }

    public function setCnpj(?string $cnpj): self
    {
        $this->cnpj = $cnpj ?: null;
        $this->touch();
        return $this;
    }

    public function getDataCadastro(): ?\DateTimeImmutable
    {
        return $this->dataCadastro;
    }

    public function setDataCadastro(?\DateTimeImmutable $dataCadastro): self
    {
        $this->dataCadastro = $dataCadastro;
        $this->touch();
        return $this;
    }

    public function getValorTotal(): ?string
    {
        return $this->valorTotal;
    }

    public function setValorTotal(null|string|float|int $valorTotal): self
    {
        $this->valorTotal = $valorTotal === null || $valorTotal === '' ? null : number_format((float) $valorTotal, 2, '.', '');
        $this->touch();
        return $this;
    }

    public function getQtdePedidos(): ?int
    {
        return $this->qtdePedidos;
    }

    public function setQtdePedidos(?int $qtdePedidos): self
    {
        $this->qtdePedidos = $qtdePedidos;
        $this->touch();
        return $this;
    }

    public function getObservacao(): ?string
    {
        return $this->observacao;
    }

    public function setObservacao(?string $observacao): self
    {
        $this->observacao = $observacao ?: null;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
