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

namespace Tests\Centreon\Infrastructure\MonitoringServer;

use Centreon\Domain\MonitoringServer\Exception\IncompleteGenerationException;
use Centreon\Infrastructure\MonitoringServer\ConfigurationGenerationStatus;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Centreon\Infrastructure\MonitoringServer\ConfigurationGenerationStatus
 */
class ConfigurationGenerationStatusTest extends TestCase
{
    private string $generatePath;

    private ConfigurationGenerationStatus $status;

    protected function setUp(): void
    {
        $this->generatePath = sys_get_temp_dir() . '/generation-status-' . uniqid();
        mkdir($this->generatePath);
        $this->status = new ConfigurationGenerationStatus($this->generatePath);
    }

    protected function tearDown(): void
    {
        // markStarted() may have created a missing-directory subpath (MON-214629): remove files before directories.
        foreach (glob($this->generatePath . '/*') ?: [] as $path) {
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($this->generatePath);
    }

    private function removeDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $child) {
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }
        rmdir($path);
    }

    public function testNeverGeneratedPollerIsAccepted(): void
    {
        // Pollers generated before this check existed keep the previous behaviour
        $this->status->assertCompleted(1, 'Central');
        $this->addToAssertionCount(1);
    }

    public function testCompletedGenerationIsAccepted(): void
    {
        $this->status->markStarted(1, 'admin');
        $this->status->markCompleted(1);

        $this->status->assertCompleted(1, 'Central');
        $this->addToAssertionCount(1);
    }

    public function testGenerationThatDidNotCompleteIsRefused(): void
    {
        // e.g. PHP memory exhausted in the middle of the generation: markCompleted() never runs
        $this->status->markStarted(1, 'admin');

        try {
            $this->status->assertCompleted(1, 'Central');
            $this->fail('An IncompleteGenerationException was expected');
        } catch (IncompleteGenerationException $ex) {
            $this->assertMatchesRegularExpression(
                '/monitoring server "Central" did not complete \(started \S+ admin\).*generate its configuration again$/',
                $ex->getMessage()
            );
            // The marker path is for the logs only, never shown to the user
            $this->assertStringNotContainsString($this->generatePath, $ex->getMessage());
            $this->assertSame(IncompleteGenerationException::REASON, $ex->getCode());
        }
    }

    public function testOnlyThePollerWhoseGenerationDidNotCompleteIsRefused(): void
    {
        $this->status->markStarted(2, 'admin');
        $this->status->markStarted(1, 'admin');
        $this->status->markCompleted(1);

        $this->status->assertCompleted(1, 'Central');
        $this->expectException(IncompleteGenerationException::class);
        $this->status->assertCompleted(2, 'Poller');
    }

    public function testANewCompleteGenerationClearsAFailedOne(): void
    {
        $this->status->markStarted(1, 'root');
        $this->status->markStarted(1, 'admin');
        $this->status->markCompleted(1);

        $this->status->assertCompleted(1, 'Central');
        $this->assertSame([], glob($this->generatePath . '/*'));
    }

    public function testUnwritableMarkerDoesNotBlockTheGeneration(): void
    {
        $status = new ConfigurationGenerationStatus($this->generatePath . '/missing-directory');

        $status->markStarted(1, 'admin');
        $status->markCompleted(1);
        $status->assertCompleted(1, 'Central');
        $this->addToAssertionCount(1);
    }

    public function testMarkStartedCreatesTheMarkerDirectoryWhenMissing(): void
    {
        $generatePath = $this->generatePath . '/missing-directory';
        $status = new ConfigurationGenerationStatus($generatePath);

        $status->markStarted(1, 'admin');

        $this->assertFileExists($generatePath . '/1.generation-incomplete');
    }
}
