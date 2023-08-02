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

declare(strict_types=1);

namespace Core\Notification\Infrastructure\Repository;

use Core\Notification\Application\Repository\NotifiableResourceRequestProviderInterface;

class HostGroupRequestProvider implements NotifiableResourceRequestProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getNotifiableResourceSubRequest(): string
    {
        return <<<'SQL'
            SELECT n.`id` AS `notification_id`,
                h.`host_id` AS `host_id`,
                h.`host_name` AS `host_name`,
                h.`host_alias` AS `host_alias`,
                n.`hostgroup_events` AS `host_events`,
                hsr.`service_service_id` AS `service_id`,
                s.`service_description` AS `service_name`,
                s.`service_alias` AS `service_alias`,
                0 AS `service_events`,
                n.`included_service_events`
            FROM `:db`.`host` h
                INNER JOIN `:db`.`hostgroup_relation` hgr ON hgr.`host_host_id` = h.`host_id`
                INNER JOIN `:db`.`host_service_relation` hsr ON hsr.`host_host_id` = h.`host_id`
                INNER JOIN `:db`.`notification_hg_relation` nhgr ON nhgr.`hg_id` = hgr.`hostgroup_hg_id`
                INNER JOIN `:db`.`notification` n ON n.`id` = nhgr.`notification_id`
                INNER JOIN `:db`.`service` s ON s.`service_id` = hsr.`service_service_id`
            WHERE n.`is_activated` = 1
            SQL;
    }
}
