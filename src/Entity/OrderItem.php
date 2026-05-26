<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource]
#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Orders::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Orders $order = null;

    #[ORM\ManyToOne(targetEntity: Products::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read', 'order:write'])]
    private ?Products $product = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['order:read', 'order:write'])]
    private ?int $quantity = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Orders
    {
        return $this->order;
    }

    public function setOrder(?Orders $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProduct(): ?Products
    {
        return $this->product;
    }

    public function setProduct(?Products $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getSubtotal(): float
    {
        $product = $this->getProduct();
        $quantity = $this->getQuantity() ?? 0;
        
        if (!$product) {
            return 0.0;
        }

        return (float) $product->getPrice() * $quantity;
    }
}
