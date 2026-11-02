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
        $events = [];
        for ($index = $dice->int(0, 2); $index > 0; --$index) {
            $path = sprintf('events[%d]', count($events));
            $events[] = $document->tagged(EventLog::class, $path, self::event($document), $type);
        }
        $log = new EventLog($document->tagged(EventLog::class, 'latest', self::event($document), $type), $events);
        $schema = Tag::of('schema', "'v1', 'v2'", 'v3');
        for ($index = $dice->int(0, 2); $index > 0; --$index) {
            $path = sprintf('archived[%d]', count($log->archived));
            $log->archived[] = $document->tagged(EventLog::class, $path, self::event($document), $schema);
        }
        return $log;
    }

    private static function event(Document $document): Created|Deleted
    {
        $id = 'id-' . $document->dice->int(0, 99);
        $created = $document->dice->chance(50);
        return $created ? new Created('created', 'v1', $id) : new Deleted('deleted', 'v2', $id);
    }
}
