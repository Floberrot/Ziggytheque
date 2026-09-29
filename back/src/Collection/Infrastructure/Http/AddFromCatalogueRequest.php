<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

/** A series picked in the catalogue, and the tomes the user owns of it. */
final readonly class AddFromCatalogueRequest
{
    private const int MAX_VOLUMES = 500;

    /**
     * @param list<array{number: int, isbn?: string|null, coverUrl?: string|null}> $volumes
     * @param list<int>                                                           $ownedNumbers
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $workTitle = '',
        #[Assert\Length(max: 100)]
        public ?string $publisher = null,
        #[Assert\Length(max: 150)]
        public ?string $specialEdition = null,
        #[Assert\Length(max: 255)]
        public ?string $author = null,
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 255)]
        public ?string $coverUrl = null,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(self::MAX_VOLUMES)]
        public int $volumeCount = 1,
        #[Assert\Count(max: self::MAX_VOLUMES)]
        #[Assert\All([
            new Assert\Collection(
                fields: [
                    'number'   => [
                        new Assert\NotNull(),
                        new Assert\Type('integer'),
                        new Assert\Positive(),
                        new Assert\LessThanOrEqual(self::MAX_VOLUMES),
                    ],
                    'isbn'     => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 20)]),
                    'coverUrl' => new Assert\Optional([
                        new Assert\Type('string'),
                        new Assert\Url(protocols: ['https'], requireTld: true),
                        new Assert\Length(max: 255),
                    ]),
                ],
                allowExtraFields: true,
            ),
        ])]
        public array $volumes = [],
        #[Assert\Count(max: self::MAX_VOLUMES)]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
            new Assert\LessThanOrEqual(self::MAX_VOLUMES),
        ])]
        public array $ownedNumbers = [],
    ) {
    }
}
