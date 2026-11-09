<?php

declare(strict_types=1);

namespace Checkslip;

/**
 * One page of a list. Iterating a Page (foreach) walks the items of this page only;
 * {@see Page::autoPaging()} walks every item across all pages and fetches the
 * next page only when it gets there.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T> $items
     * @param (\Closure(string): Page<T>)|null $fetchNext Fetches the page after $nextCursor.
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
        private readonly ?\Closure $fetchNext = null,
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null && $this->nextCursor !== '';
    }

    /**
     * Fetches the next page, or returns null on the last one.
     *
     * @return Page<T>|null
     */
    public function nextPage(): ?self
    {
        if (!$this->hasMore() || $this->fetchNext === null) {
            return null;
        }

        return ($this->fetchNext)((string) $this->nextCursor);
    }

    /**
     * Every item from this page on, across all following pages.
     *
     * @return \Generator<int, T>
     */
    public function autoPaging(): \Generator
    {
        $page = $this;
        while ($page !== null) {
            foreach ($page->items as $item) {
                yield $item;
            }
            $page = $page->nextPage();
        }
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
