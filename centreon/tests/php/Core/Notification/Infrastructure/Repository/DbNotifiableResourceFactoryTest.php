<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\Notification\Infrastructure\Repository;

use Core\Notification\Domain\Model\{NotifiableHost, NotifiableResource, NotifiableService, NotificationHostEvent, NotificationServiceEvent};
use Core\Notification\Infrastructure\Repository\DbNotifiableResourceFactory;

dataset('$records', [
    [
        [
            [
                'notification_id' => 1,
                'host_id' => 16,
                'host_name' => 'myHost',
                'host_alias' => 'myHost',
                'host_events' => 0,
                'service_id' => 27,
                'service_name' => 'Ping',
                'service_alias' => null,
                'service_events' => 1,
                'included_service_events' => 0
            ],
            [
                'notification_id' => 2,
                'host_id' => 17,
                'host_name' => 'myHost2',
                'host_alias' => 'myHost2',
                'host_events' => 6,
                'service_id' => 34,
                'service_name' => 'Ping',
                'service_alias' => null,
                'service_events' => 0,
                'included_service_events' => 6
            ],
            [
                'notification_id' => 2,
                'host_id' => 17,
                'host_name' => 'myHost2',
                'host_alias' => 'myHost2',
                'host_events' => 6,
                'service_id' => 35,
                'service_name' => 'Disk-/',
                'service_alias' => null,
                'service_events' => 0,
                'included_service_events' => 6
            ],
            [
                'notification_id' => 3,
                'host_id' => 18,
                'host_name' => 'myHost3',
                'host_alias' => 'myHost3',
                'host_events' => 4,
                'service_id' => 36,
                'service_name' => 'Ping',
                'service_alias' => null,
                'service_events' => 0,
                'included_service_events' => 0
            ]
        ]
    ]
]);

it('can create an array of notifiable resources from an array of records', function (array $records): void {
    $notifiableResources = DbNotifiableResourceFactory::createFromRecords($records);
    foreach ($notifiableResources as $notifiableResource) {
        expect($notifiableResource)->toBeInstanceOf(NotifiableResource::class);
        expect($notifiableResource->getHosts())->toBeArray();
        foreach ($notifiableResource->getHosts() as $host) {
            expect($host)->toBeInstanceOf(NotifiableHost::class);
            expect($host->getEvents())->toBeArray();
            foreach ($host->getEvents() as $hostEvent) {
                expect($hostEvent)->toBeInstanceOf(NotificationHostEvent::class);
            }
            expect($host->getServices())->toBeArray();
            foreach ($host->getServices() as $service) {
                expect($service)->toBeInstanceOf(NotifiableService::class);
                expect($service->getEvents())->toBeArray();
                foreach ($service->getEvents() as $serviceEvent) {
                    expect($serviceEvent)->toBeInstanceOf(NotificationServiceEvent::class);
                }
            }
        }
    }
})->with('$records');
