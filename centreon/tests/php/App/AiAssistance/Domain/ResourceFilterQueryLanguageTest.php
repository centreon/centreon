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

namespace Tests\App\AiAssistance\Domain;

use App\AiAssistance\Domain\ResourceFilterQueryLanguage;
use PHPUnit\Framework\TestCase;

final class ResourceFilterQueryLanguageTest extends TestCase
{
    public function testValidExpressionsPass(): void
    {
        $expressions = [
            '',
            'type:service status:critical state:unhandled',
            'type:host status:down,unreachable host_group:Linux\sServers',
            'name:ping parent_name:db',
            'h.name:^web information:timeout',
            'free text search',
            'status_type:hard monitoring_server:central',
            'host_severity_level:1 service_severity:Critical\sBusiness',
        ];

        foreach ($expressions as $expression) {
            self::assertNull(
                ResourceFilterQueryLanguage::validate($expression),
                sprintf("expression '%s' should be valid", $expression)
            );
        }
    }

    public function testUnknownKeyIsRejected(): void
    {
        $error = ResourceFilterQueryLanguage::validate('hostgroup:Linux');

        self::assertNotNull($error);
        self::assertStringContainsString("unknown key 'hostgroup'", $error);
    }

    public function testInvalidStaticValueIsRejected(): void
    {
        $error = ResourceFilterQueryLanguage::validate('status:broken');

        self::assertNotNull($error);
        self::assertStringContainsString("invalid value 'broken'", $error);
    }

    public function testEmptyValueIsRejected(): void
    {
        $error = ResourceFilterQueryLanguage::validate('host_group:');

        self::assertNotNull($error);
        self::assertStringContainsString('has no value', $error);
    }
}
