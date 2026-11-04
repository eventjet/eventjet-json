<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases\Discriminated;

use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Created;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\Deleted;
use Eventjet\Json\Test\Acceptance\Fixtures\Discriminated\EventLog;

use function count;
use function sprintf;

/** @internal */
final class EventDocuments
{
    public static function eventLog(Document $document): EventLog
    {
        $dice = $document->dice;
        $type = new Tag('@type', "'created', 'deleted'", 'updated', 'type');
        $events = self::events($document, 'events', $type);
        $log = new EventLog($document->tagged(EventLog::class, 'latest', self::event($document), $type), $events);
        $schema = Tag::of('schema', "'v1', 'v2'", 'v3');
        $log->archived = self::events($document, 'archived', $schema);
        for ($index = $dice->int(0, 2); $index > 0; --$index) {
            $log->batches[] = self::events($document, sprintf('batches[%d]', count($log->batches)), $schema);
        }
        $paired = $dice->chance(50);
        if ($paired) {
            $first = $document->tagged(EventLog::class, 'pair[0]', self::event($document), $schema);
            $log->pair = [$first, self::events($document, 'pair[1]', $schema)];
        }
        return $log;
    }

    /** @return list<Created|Deleted> */
    private static function events(Document $document, string $path, Tag $tag): array
    {
        $events = [];
        for ($index = $document->dice->int(0, 2); $index > 0; --$index) {
            $item = sprintf('%s[%d]', $path, count($events));
            $events[] = $document->tagged(EventLog::class, $item, self::event($document), $tag);
        }
        return $events;
    }

    private static function event(Document $document): Created|Deleted
    {
        $id = 'id-' . $document->dice->int(0, 99);
        $created = $document->dice->chance(50);
        return $created ? new Created('created', 'v1', $id) : new Deleted('deleted', 'v2', $id);
    }
}
