<?php

declare(strict_types=1);

namespace App\PriceCode\Domain;

interface PriceCodeRepositoryInterface
{
    public function findByCode(string $code): ?PriceCode;

    /** @return PriceCode[] */
    public function findAll(): array;

    public function save(PriceCode $priceCode): void;

    public function delete(PriceCode $priceCode): void;
}
