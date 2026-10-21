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

namespace CentreonLegacy\Core\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CacheClearerTest extends TestCase
{
    public function testClearsBothKernelCaches(): void
    {
        $clearer = $this->recordingClearer();

        $clearer->clear();

        $this->assertSame(['bin/console cache:clear', 'bin/console.new cache:clear'], $clearer->commands);
    }

    public function testOnlyAFailureOfTheLegacyKernelIsFatal(): void
    {
        $newKernelFailing = $this->failingClearer('bin/console.new');
        $newKernelFailing->clear();
        $this->assertSame(['bin/console cache:clear', 'bin/console.new cache:clear'], $newKernelFailing->commands);

        $legacyKernelFailing = $this->failingClearer('bin/console');
        try {
            $legacyKernelFailing->clear();
            $this->fail('The failure of the legacy kernel must be reported');
        } catch (ProcessFailedException) {
        }
        $this->assertSame(['bin/console cache:clear', 'bin/console.new cache:clear'], $legacyKernelFailing->commands, 'The new kernel is still cleared');
    }

    public function testAFailingConsoleCommandThrows(): void
    {
        $this->expectException(ProcessFailedException::class);

        // The real command runner, against a console that does not exist.
        (new \ReflectionMethod(CacheClearer::class, 'run'))->invoke(new CacheClearer(), ['bin/does-not-exist']);
    }

    public function testIgnoresTheClearsRequestedWhileSuspended(): void
    {
        $clearer = $this->recordingClearer();

        $result = $clearer->suspended(function () use ($clearer): string {
            $clearer->clear();
            $clearer->suspended($clearer->clear(...));
            $clearer->clear();

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame([], $clearer->commands);

        $clearer->clear();
        $this->assertCount(2, $clearer->commands, 'Clears are honoured again once the suspension ends');
    }

    public function testResumesClearingWhenTheSuspendedCallbackFails(): void
    {
        $clearer = $this->recordingClearer();

        try {
            $clearer->suspended(static fn () => throw new \RuntimeException('module upgrade failed'));
            $this->fail('The failure must propagate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('module upgrade failed', $exception->getMessage());
        }

        $clearer->clear();
        $this->assertCount(2, $clearer->commands);
    }

    /**
     * @return CacheClearer&object{commands: list<string>}
     */
    private function failingClearer(string $failingConsole): CacheClearer
    {
        return new class ($failingConsole) extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            public function __construct(private readonly string $failingConsole)
            {
            }

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
                if ($command[0] === $this->failingConsole) {
                    $process = new Process(['false']);
                    $process->run();

                    throw new ProcessFailedException($process);
                }
            }
        };
    }

    /**
     * @return CacheClearer&object{commands: list<string>}
     */
    private function recordingClearer(): CacheClearer
    {
        return new class () extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
            }
        };
    }
}
