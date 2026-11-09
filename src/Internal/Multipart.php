<?php

declare(strict_types=1);

namespace Checkslip\Internal;

use Checkslip\Upload;

/**
 * multipart/form-data body for the image lookup.
 *
 * @internal
 */
final class Multipart
{
    /**
     * @param array<string, string|null> $fields
     *
     * @return array{body: string, contentType: string}
     */
    public static function build(Upload $file, string $fileField, array $fields = []): array
    {
        $boundary = 'checkslip-' . bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }
            $body .= "--{$boundary}\r\n"
                . "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n"
                . $value . "\r\n";
        }
        $body .= "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"{$fileField}\"; filename=\"{$file->filename}\"\r\n"
            . "Content-Type: {$file->contentType}\r\n\r\n"
            . $file->contents . "\r\n"
            . "--{$boundary}--\r\n";

        return ['body' => $body, 'contentType' => 'multipart/form-data; boundary=' . $boundary];
    }
}
