<?php

declare(strict_types=1);

namespace Checkslip\Tests\Unit;

use Checkslip\Model\Endpoint;
use Checkslip\Tests\TestCase;

final class PaginationTest extends TestCase
{
    public function testAutoPagingFollowsNextCursorLazily(): void
    {
        $ep = self::fixture('endpoint');
        $this->http->addResponse(self::json(200, ['items' => [$ep, $ep], 'next_cursor' => 'c2']));
        $this->http->addResponse(self::json(200, ['items' => [$ep], 'next_cursor' => 'c3']));
        $this->http->addResponse(self::json(200, ['items' => [$ep], 'next_cursor' => null]));

        $page = $this->client()->webhooks->endpoints->list(limit: 2);
        self::assertCount(1, $this->http->getRequests(), 'only the first page so far');
        self::assertTrue($page->hasMore());
        self::assertSame('c2', $page->nextCursor);

        $seen = 0;
        foreach ($page->autoPaging() as $endpoint) {
            self::assertInstanceOf(Endpoint::class, $endpoint);
            ++$seen;
            if ($seen === 2) {
                self::assertCount(1, $this->http->getRequests(), 'page 2 is fetched only when reached');
            }
        }
        self::assertSame(4, $seen);

        $requests = $this->http->getRequests();
        self::assertCount(3, $requests);
        self::assertSame('limit=2', $requests[0]->getUri()->getQuery());
        self::assertSame('cursor=c2&limit=2', $requests[1]->getUri()->getQuery());
        self::assertSame('cursor=c3&limit=2', $requests[2]->getUri()->getQuery());
    }

    public function testNextPageOnTheLastPageIsNull(): void
    {
        $this->http->addResponse(self::json(200, ['items' => [], 'next_cursor' => null]));

        $page = $this->client()->webhooks->endpoints->list();

        self::assertFalse($page->hasMore());
        self::assertNull($page->nextPage());
        self::assertSame([], iterator_to_array($page));
        self::assertCount(1, $this->http->getRequests());
    }

    public function testCursorReplacesTheOneGivenAtFirst(): void
    {
        $ep = self::fixture('endpoint');
        $this->http->addResponse(self::json(200, ['items' => [$ep], 'next_cursor' => 'next']));
        $this->http->addResponse(self::json(200, ['items' => [$ep], 'next_cursor' => null]));

        $page = $this->client()->webhooks->endpoints->list(cursor: 'start');
        $page->nextPage();

        $requests = $this->http->getRequests();
        self::assertSame('cursor=start', $requests[0]->getUri()->getQuery());
        self::assertSame('cursor=next', $requests[1]->getUri()->getQuery());
    }
}
