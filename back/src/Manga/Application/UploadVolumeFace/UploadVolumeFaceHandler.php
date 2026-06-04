<?php

declare(strict_types=1);

namespace App\Manga\Application\UploadVolumeFace;

use App\Manga\Domain\MangaRepositoryInterface;
use App\Manga\Domain\Storage\ImageStorageInterface;
use App\Manga\Domain\Volume;
use App\Manga\Domain\VolumeFace;
use App\Shared\Domain\Exception\NotFoundException;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UploadVolumeFaceHandler
{
    public function __construct(
        private MangaRepositoryInterface $mangaRepository,
        private ImageStorageInterface $imageStorage,
    ) {
    }

    public function __invoke(UploadVolumeFaceCommand $command): void
    {
        $manga = $this->mangaRepository->findById($command->mangaId);

        if ($manga === null) {
            throw new NotFoundException('Manga', $command->mangaId);
        }

        $volume = $manga->volumes
            ->filter(fn (Volume $volume) => $volume->id === $command->volumeId)
            ->first();

        if ($volume === false) {
            throw new NotFoundException('Volume', $command->volumeId);
        }

        $binary = base64_decode($this->stripDataUrlPrefix($command->imageBase64), true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Invalid base64 image payload.');
        }

        $key = sprintf(
            'volume-faces/%s-%s-%s.%s',
            $command->volumeId,
            $command->face->value,
            bin2hex(random_bytes(4)),
            $this->extensionFor($command->contentType),
        );

        $url = $this->imageStorage->store($key, $binary, $command->contentType);

        if ($command->face === VolumeFace::Cover) {
            $volume->coverUrl = $url;
        } elseif ($command->face === VolumeFace::Spine) {
            $volume->spineUrl = $url;
        } else {
            $volume->backCoverUrl = $url;
        }

        $this->mangaRepository->save($manga);
    }

    private function stripDataUrlPrefix(string $payload): string
    {
        if (str_starts_with($payload, 'data:')) {
            $comma = strpos($payload, ',');
            if ($comma !== false) {
                return substr($payload, $comma + 1);
            }
        }

        return $payload;
    }

    private function extensionFor(string $contentType): string
    {
        return match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => 'jpg',
        };
    }
}
