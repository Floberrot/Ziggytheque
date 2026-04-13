<?php

declare(strict_types=1);

namespace App\PriceCode\Infrastructure\Doctrine;

use App\PriceCode\Domain\PriceCode;
use App\PriceCode\Domain\PriceCodeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceCodeRepository implements PriceCodeRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findByCode(string $code): ?PriceCode
    {
        return $this->em->find(PriceCode::class, $code);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(PriceCode::class)->findBy([], ['code' => 'ASC']);
    }

    public function save(PriceCode $priceCode): void
    {
        $this->em->persist($priceCode);
        $this->em->flush();
    }

    public function delete(PriceCode $priceCode): void
    {
        $this->em->remove($priceCode);
        $this->em->flush();
    }
}
