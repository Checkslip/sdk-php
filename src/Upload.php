<?php

declare(strict_types=1);

namespace Checkslip;

use Psr\Http\Message\StreamInterface;

/**
 * A slip image to send to {@see Resource\Slips::lookupImage()}.
 *
 * The API takes JPEG, PNG or WebP up to 10 MB and 50 megapixels. The content
 * type is read from the file's first bytes, so the file name does not matter.
 * Checkslip does not store the image, only the QR text found in it.
 */
final class Upload
{
    private function __construct(
        public readonly string $contents,
        public readonly string $filename,
        public readonly string $contentType,
    ) {}

    /**
     * Reads an image file from disk.
     */
    public static function fromPath(string $path, ?string $contentType = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(\sprintf('Cannot read the image file "%s".', $path));
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \InvalidArgumentException(\sprintf('Cannot read the image file "%s".', $path));
        }

        return self::fromString($contents, basename($path), $contentType);
    }

    /**
     * Uses image bytes you already hold in memory, for example from an upload form.
     */
    public static function fromString(string $contents, string $filename = 'slip', ?string $contentType = null): self
    {
        if ($contents === '') {
            throw new \InvalidArgumentException('The image is empty.');
        }

        return new self($contents, self::cleanName($filename), $contentType ?? self::sniff($contents));
    }

    /**
     * Reads a PHP stream resource or a PSR-7 stream from its current position to the end.
     *
     * @param resource|StreamInterface $stream
     */
    public static function fromStream(mixed $stream, string $filename = 'slip', ?string $contentType = null): self
    {
        if ($stream instanceof StreamInterface) {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $contents = $stream->getContents();
        } elseif (\is_resource($stream)) {
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                throw new \InvalidArgumentException('Cannot read the image stream.');
            }
        } else {
            throw new \InvalidArgumentException(\sprintf('Expected a stream resource or %s, got %s.', StreamInterface::class, get_debug_type($stream)));
        }

        return self::fromString($contents, $filename, $contentType);
    }

    private static function sniff(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            // Not a type the API takes. Send it anyway and let the API answer with
            // INVALID_FILE, which uses no credit, instead of guessing here.
            default => 'application/octet-stream',
        };
    }

    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $name) ?? 'slip';

        return $name === '' ? 'slip' : $name;
    }
}
