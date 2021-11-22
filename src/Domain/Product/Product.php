<?php
declare(strict_types=1);

namespace App\Domain\Product;

use JsonSerializable;

class Product implements JsonSerializable
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string
     */
    private $sku;

    /**
     * @var string
     */
    private $attributes;

    /**
     * @param int|null  $id
     * @param string    $sku
     * @param string    $attributes
     */
    public function __construct(?int $id, string $sku, string $attributes)
    {
        $this->id = $id;
        $this->sku = $sku;
        $this->attributes = $attributes;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @return string
     */
    public function getAttributes(): string
    {
        return $this->attributes;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'attributes' => $this->attributes
        ];
    }
}