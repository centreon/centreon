<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\AiConfiguration\Infrastructure\Llm;

use App\AiConfiguration\Infrastructure\Llm\ServerSentEventParser;
use PHPUnit\Framework\TestCase;

final class ServerSentEventParserTest extends TestCase
{
    public function testParsesCompleteEvents(): void
    {
        $parser = new ServerSentEventParser();

        $payloads = $parser->push("event: message\ndata: {\"a\":1}\n\ndata: {\"b\":2}\n\n");

        self::assertSame(['{"a":1}', '{"b":2}'], $payloads);
    }

    public function testBuffersEventsSplitAcrossChunks(): void
    {
        $parser = new ServerSentEventParser();

        self::assertSame([], $parser->push('data: {"a"'));
        self::assertSame(['{"a":1}'], $parser->push(":1}\n\n"));
    }

    public function testSupportsCrLfLineEndings(): void
    {
        $parser = new ServerSentEventParser();

        $payloads = $parser->push("data: {\"a\":1}\r\n\r\n");

        self::assertSame(['{"a":1}'], $payloads);
    }

    public function testIgnoresCommentsAndEventsWithoutData(): void
    {
        $parser = new ServerSentEventParser();

        $payloads = $parser->push(": keep-alive\n\nevent: ping\n\ndata: ok\n\n");

        self::assertSame(['ok'], $payloads);
    }
}
