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

namespace Tests\Centreon\Application\Controller\Configuration;

use Centreon\Application\Controller\Configuration\MonitoringServerController;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Domain\MonitoringServer\Exception\IncompleteGenerationException;
use Centreon\Domain\MonitoringServer\Exception\MonitoringServerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * HTTP status of the configuration export errors: the code of the MonitoringServerException is the status of the
 * response (CentreonEventSubscriber).
 *
 * @covers \Centreon\Application\Controller\Configuration\MonitoringServerController
 */
class MonitoringServerControllerTest extends TestCase
{
    #[DataProvider('provideExportErrors')]
    public function testExportErrorStatus(\Exception $error, int $expectedStatus, string $expectedMessage): void
    {
        $response = (new \ReflectionMethod(MonitoringServerController::class, 'toResponseException'))
            ->invoke(null, $error);

        $this->assertInstanceOf(MonitoringServerException::class, $response);
        $this->assertSame($expectedStatus, $response->getCode());
        $this->assertSame($expectedMessage, $response->getMessage());
        $this->assertSame($error, $response->getPrevious());
    }

    /**
     * @return iterable<string, array{\Exception, int, string}>
     */
    public static function provideExportErrors(): iterable
    {
        yield 'last generation incomplete' => [
            new IncompleteGenerationException('generation did not complete', IncompleteGenerationException::REASON),
            409,
            'generation did not complete',
        ];

        yield 'poller not connected to gorgone' => [
            new GorgoneDeliveryException('node 2 not connected', GorgoneDeliveryException::NODE_NOT_CONNECTED),
            409,
            'node 2 not connected',
        ];

        yield 'failure reported by gorgone' => [
            new GorgoneDeliveryException('no pathway', GorgoneDeliveryException::DELIVERY_FAILED),
            409,
            'no pathway',
        ];

        yield 'gorgone result not confirmed in time' => [
            new GorgoneDeliveryException('not confirmed', GorgoneDeliveryException::RESULT_TIMEOUT),
            504,
            'not confirmed',
        ];

        yield 'generation timeout' => [
            new TimeoutException('generation did not finish within 60 seconds'),
            504,
            'generation did not finish within 60 seconds',
        ];

        yield 'generation failure' => [
            ConfigurationMonitoringServerException::errorOnGeneration(1, 'Request failed (500)'),
            500,
            'Generation error on monitoring server #1: Request failed (500)',
        ];

        yield 'anything else' => [
            new \RuntimeException('unexpected'),
            500,
            'There was an consistency error in the exported files - please use the legacy export menu to troubleshoot',
        ];
    }
}
