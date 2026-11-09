<?php

declare(strict_types=1);

namespace Checkslip\Resource;

use Checkslip\Internal\Arr;
use Checkslip\Internal\Query;
use Checkslip\Internal\Transport;
use Checkslip\Internal\Uuid;
use Checkslip\Page;

/**
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport) {}

    /**
     * GETs a cursor-paged list and wraps it in a Page that can fetch the next one.
     *
     * @template T
     *
     * @param array<string, scalar|\DateTimeInterface|list<string>|null> $query
     * @param \Closure(array<string, mixed>): T $map
     *
     * @return Page<T>
     */
    protected function page(string $path, array $query, \Closure $map): Page
    {
        [$data] = $this->transport->json('GET', $path, $query);
        $items = array_map($map, Arr::objects($data, 'items'));

        return new Page(
            $items,
            Arr::strOrNull($data, 'next_cursor'),
            fn(string $cursor): Page => $this->page($path, ['cursor' => $cursor] + $query, $map),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetch(string $path): array
    {
        return $this->transport->json('GET', $path)[0];
    }

    /**
     * @return array<string, string>
     */
    protected static function idempotency(?string $key): array
    {
        return ['Idempotency-Key' => $key ?? Uuid::v4()];
    }

    /**
     * The charged filter takes true, false and "unsettled" as strings.
     *
     * @param list<bool|string>|null $charged
     *
     * @return list<string>|null
     */
    protected static function charged(?array $charged): ?array
    {
        return Query::list($charged === null ? null : array_map(static fn(bool|string $c): string => \is_bool($c) ? ($c ? 'true' : 'false') : $c, $charged));
    }

    protected static function path(string $format, string|int ...$ids): string
    {
        return vsprintf($format, array_map(static fn(string|int $id): string => rawurlencode((string) $id), $ids));
    }
}
