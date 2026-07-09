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

namespace Tests\App\Monitoring\Infrastructure\ApiPlatform\State;

use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationName;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriod;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\Monitoring\Domain\Repository\NotificationRepository;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\NotificationResource;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\ApiTestCase;

/*
 * Test à faire
 * Get => trouve pas (404)
 * Get => trouve (200)
 * Get => pas de droit (403)
 * Get => sans connexion (401)
 *
 * Test de repository dans le dossier Dbal
 */
final class FindNotificationProviderTest extends ApiTestCase
{
    public function testItFindNotificationThatExist(): void
    {
        /** @var NotificationRepository $repository */
        $repository = self::getContainer()->get(NotificationRepository::class);
        $repository->add(
            $notification = new Notification(
                null, new NotificationName('hello world!'), true, new TimePeriod(
                    new TimePeriodId(1), new TimePeriodName('24x7'),
                ),
            )
        );
        $this->login();
        $this->request('GET', '/api/latest/configuration/notifications/' . $notification->id()->value);
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceItemJsonSchema(NotificationResource::class);
        self::assertJsonContains(
            [
                'name' => 'hello world!',
                'is_activated' => true,
                'timeperiod' => [
                    'id' => 1,
                    'name' => '24x7',
                ],
            ]
        );
    }

    public function testItFindNotificationThatNotExist(): void
    {
        $this->login();
        $this->request('GET', '/api/latest/configuration/notifications/99999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItFindNotificationWhenNotAuthenticated(): void
    {
        $this->request('GET', '/api/latest/configuration/notifications/1');
        self::assertResponseStatusCodeSame(401);
    }

    public function testItFindNotificationWhenNotUsedAsNoPermissions(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $username = bin2hex(random_bytes(8));
        $this->createApiUser($connection, $username, admin: false);
        $this->login($username);

        $this->request('GET', '/api/latest/configuration/notifications/1');
        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains(
            [
                'code' => 0,
                'message' => 'User doesn\'t have sufficient rights to get notification',
            ]
        );
    }
}
