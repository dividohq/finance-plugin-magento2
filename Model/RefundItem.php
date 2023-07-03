<?php

namespace Divido\DividoFinancing\Model;

class RefundItem
{

    private string $name;

    private int $amount;

    private int $quantity;

    public function __construct(
        string $name,
        int $amount,
        int $quantity
    ){
        $this->name = $name;
        $this->amount = $amount;
        $this->quantity = $quantity;
    }

    /**
     * Get the value of name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the value of amount
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * Get the value of quantity
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}