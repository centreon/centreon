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

namespace Tests\www\class;

use CentreonDB;
use CentreonDBStatement;
use CentreonDowntime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CentreonDowntimeTest extends TestCase
{
    /** Records returned by the service group downtimes query */
    private CentreonDBStatement&MockObject $serviceGroupStatement;

    /** Services inheriting from the service templates found in the service groups */
    private CentreonDBStatement&MockObject $templateServicesStatement;

    private CentreonDowntime $downtime;

    protected function setUp(): void
    {
        $this->serviceGroupStatement = $this->createMock(CentreonDBStatement::class);
        $this->templateServicesStatement = $this->createMock(CentreonDBStatement::class);

        $db = $this->createMock(CentreonDB::class);
        $db->method('query')->willReturn($this->serviceGroupStatement);
        $db->method('prepare')->willReturn($this->templateServicesStatement);

        $this->downtime = new CentreonDowntime($db);
    }

    public function testEveryDowntimeTargetingTheSameServiceTemplateIsApplied(): void
    {
        $this->serviceGroupStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->serviceGroupDowntimeRecord(['dt_id' => '12', 'dtp_day_of_week' => '1,2,3,4,5']),
            $this->serviceGroupDowntimeRecord(['dt_id' => '22', 'dtp_day_of_week' => '6,7']),
            false
        );
        $this->templateServicesStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->templateService('1', '1001', '100'),
            $this->templateService('2', '1002', '100'),
            false
        );

        $downtimes = $this->downtime->getForEnabledServicegroups();

        $this->assertEqualsCanonicalizing(
            [
                '12-1001-1,2,3,4,5',
                '12-1002-1,2,3,4,5',
                '22-1001-6,7',
                '22-1002-6,7',
            ],
            $this->downtimeSignatures($downtimes)
        );
    }

    public function testEveryPeriodOfADowntimeTargetingAServiceTemplateIsApplied(): void
    {
        $this->serviceGroupStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->serviceGroupDowntimeRecord(['dt_id' => '12', 'dtp_day_of_week' => '1,2,3,4,5']),
            $this->serviceGroupDowntimeRecord(['dt_id' => '12', 'dtp_day_of_week' => '6,7']),
            false
        );
        $this->templateServicesStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->templateService('1', '1001', '100'),
            false
        );

        $downtimes = $this->downtime->getForEnabledServicegroups();

        $this->assertEqualsCanonicalizing(
            ['12-1001-1,2,3,4,5', '12-1001-6,7'],
            $this->downtimeSignatures($downtimes)
        );
    }

    public function testServicesOnlyReceiveTheDowntimesOfTheirOwnServiceTemplate(): void
    {
        $this->serviceGroupStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->serviceGroupDowntimeRecord(['dt_id' => '12', 'service_id' => '100']),
            $this->serviceGroupDowntimeRecord(['dt_id' => '22', 'service_id' => '200']),
            false
        );
        $this->templateServicesStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->templateService('1', '1001', '100'),
            $this->templateService('2', '1002', '200'),
            false
        );

        $downtimes = $this->downtime->getForEnabledServicegroups();

        $this->assertEqualsCanonicalizing(
            ['12-1001-1,2,3,4,5', '22-1002-1,2,3,4,5'],
            $this->downtimeSignatures($downtimes)
        );
    }

    public function testRegularServiceDowntimesAreKeptAlongsideServiceTemplateOnes(): void
    {
        $this->serviceGroupStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->serviceGroupDowntimeRecord([
                'dt_id' => '5',
                'host_id' => '3',
                'host_name' => 'host-3',
                'service_id' => '2001',
                'service_description' => 'regular-service',
                'service_register' => '1',
            ]),
            $this->serviceGroupDowntimeRecord(['dt_id' => '12']),
            false
        );
        $this->templateServicesStatement->method('fetch')->willReturnOnConsecutiveCalls(
            $this->templateService('1', '1001', '100'),
            false
        );

        $downtimes = $this->downtime->getForEnabledServicegroups();

        $this->assertEqualsCanonicalizing(
            ['5-2001-1,2,3,4,5', '12-1001-1,2,3,4,5'],
            $this->downtimeSignatures($downtimes)
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function serviceGroupDowntimeRecord(array $overrides): array
    {
        return array_merge(
            [
                'dt_id' => '1',
                'dt_activate' => '1',
                'dtp_start_time' => '08:00:00',
                'dtp_end_time' => '18:00:00',
                'dtp_day_of_week' => '1,2,3,4,5',
                'dtp_month_cycle' => 'none',
                'dtp_day_of_month' => null,
                'dtp_fixed' => '1',
                'dtp_duration' => null,
                'host_id' => '10',
                'host_name' => 'host-template',
                'service_id' => '100',
                'service_description' => 'service-template',
                'service_register' => '0',
            ],
            $overrides
        );
    }

    /**
     * @return array<string, string>
     */
    private function templateService(string $hostId, string $serviceId, string $serviceTemplateId): array
    {
        return [
            'host_name' => 'host-' . $hostId,
            'host_id' => $hostId,
            'service_id' => $serviceId,
            'service_description' => 'service-' . $serviceId,
            'service_template_model_stm_id' => $serviceTemplateId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $downtimes
     *
     * @return list<string> "<downtime id>-<service id>-<period days of week>" signatures
     */
    private function downtimeSignatures(array $downtimes): array
    {
        return array_map(
            static fn (array $downtime): string => $downtime['dt_id']
                . '-' . $downtime['service_id']
                . '-' . $downtime['dtp_day_of_week'],
            $downtimes
        );
    }
}
