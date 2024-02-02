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
 * For more information : contact@centreon.com
 *
 */

declare(strict_types = 1);

namespace Core\Host\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Host\Domain\Model\SmallHost;

/**
 * @phpstan-type _SmallHost array{
 *      id: int,
 *      name: string,
 *      alias: string|null,
 *      ip_address: string,
 *      check_interval: int|null,
 *      retry_check_interval: int|null,
 *      is_activated: string,
 *      check_timeperiod_id: int|null,
 *      check_timeperiod_name: string|null,
 *      notification_timeperiod_id: int|null,
 *      notification_timeperiod_name: string|null,
 *      severity_id: int|null,
 *      severity_name: string|null,
 *      monitoring_server_id: int,
 *      monitoring_server_name: string,
 *      category_ids: string,
 *      hostgroup_ids: string,
 *      template_ids: string
 *  }
 */
class SmallHostFactory
{
    /**
     * @param _SmallHost $data
     *
     * @throws AssertionFailedException
     */
    public static function createFromDb(array $data): SmallHost
    {
        $severity = $data['severity_id'] !== null && $data['severity_name'] !== null
            ? new SimpleEntity(
                (int) $data['severity_id'],
                new TrimmedString($data['severity_name']),
                'Host'
            ) : null;

        $checkTimePeriod
            = $data['check_timeperiod_id'] !== null && $data['check_timeperiod_name'] !== null
                ? new SimpleEntity(
                    (int) $data['check_timeperiod_id'],
                    new TrimmedString($data['check_timeperiod_name']),
                    'Host'
                ) : null;

        $notificationTimePeriod
            = $data['notification_timeperiod_id'] !== null && $data['notification_timeperiod_name'] !== null
            ? new SimpleEntity(
                (int) $data['notification_timeperiod_id'],
                new TrimmedString($data['notification_timeperiod_name']),
                'Host'
            ) : null;

        $host = new SmallHost(
            (int) $data['id'],
            new TrimmedString($data['name']),
            $data['alias'] !== null ? new TrimmedString($data['alias']) : null ,
            new TrimmedString($data['ip_address']),
            self::intOrNull($data, 'check_interval'),
            self::intOrNull($data, 'retry_check_interval'),
            $data['is_activated'] === '1',
            new SimpleEntity(
                (int) $data['monitoring_server_id'],
                new TrimmedString($data['monitoring_server_name']),
                'Host'
            ),
            $checkTimePeriod,
            $notificationTimePeriod,
            $severity,
        );

        if ($data['category_ids'] !== null) {
            $categoryIds = explode(',', $data['category_ids']);
            foreach ($categoryIds as $categoryId) {
                $host->addCategoryId((int) $categoryId);
            }
        }
        if ($data['hostgroup_ids'] !== null) {
            $groupIds = explode(',', $data['hostgroup_ids']);
            foreach ($groupIds as $groupId) {
                $host->addGroupId((int) $groupId);
            }
        }
        if ($data['template_ids'] !== null) {
            $templateIds = explode(',', $data['template_ids']);
            foreach ($templateIds as $templateId) {
                $host->addTemplateId((int) $templateId);
            }
        }

        return $host;
    }

    /**
     * @param _SmallHost $data
     * @param string $property
     *
     * @return int|null
     */
    private static function intOrNull(array $data, string $property): ?int
    {
        return array_key_exists($property, $data) && $data[$property] !== null
            ? (int) $data[$property]
            : null;
    }
}
