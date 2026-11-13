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

namespace Tests\App\Upgrade\Infrastructure\Log;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class UpgradeLogChannelConfigurationTest extends TestCase
{
    private const CONFIG_FILE = __DIR__ . '/../../../../../../config.new/packages/monolog.yaml';

    public function testTheUpgradeLogsStayOffTheConsoleInProduction(): void
    {
        /** @var array{monolog: array{handlers: array<string, array{type?: string, channels?: list<string>}>}, 'when@prod': array{monolog: array{handlers: array<string, array{type?: string, channels?: list<string>}>}}} $config */
        $config = Yaml::parseFile(self::CONFIG_FILE);
        $handlers = array_merge($config['monolog']['handlers'], $config['when@prod']['monolog']['handlers']);

        $consoleHandlers = array_filter($handlers, static fn (array $handler): bool => ($handler['type'] ?? null) === 'console');

        self::assertNotEmpty($consoleHandlers);
        foreach ($consoleHandlers as $name => $handler) {
            self::assertContains('!upgrade', $handler['channels'] ?? [], sprintf('The "%s" console handler must exclude the upgrade channel.', $name));
        }
    }
}
