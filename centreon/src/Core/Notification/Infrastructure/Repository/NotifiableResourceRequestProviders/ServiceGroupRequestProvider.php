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

namespace Core\Notification\Infrastructure\Repository\NotifiableResourceRequestProvider;

use Core\Notification\Application\Repository\NotifiableResourceRequestProviderInterface;

class ServiceGroupRequestProvider implements NotifiableResourceRequestProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getNotifiableResourceSubRequest(): string
    {
        return <<<'SQL'
            SELECT n.`id` AS `notification_id`,
                hsr.`host_host_id` AS `host_id`,
                h.`host_name` AS `host_name`,
                h.`host_alias` AS `host_alias`,
                n.`hostgroup_events` AS `host_events`,
                s.`service_id` AS `service_id`,
                s.`service_description` AS `service_name`,
                s.`service_alias` AS `service_alias`,
                n.`servicegroup_events` AS `service_events`,
                0 AS `included_service_events`
            FROM `:db`.`service` s
                INNER JOIN `:db`.`servicegroup_relation` sgr ON sgr.`service_service_id` = s.`service_id`
                INNER JOIN `:db`.`host_service_relation` hsr ON hsr.`service_service_id` = s.`service_id`
                INNER JOIN `:db`.`notification_sg_relation` nsgr ON nsgr.`sg_id` = sgr.`servicegroup_sg_id`
                INNER JOIN `:db`.`notification` n ON n.`id` = nsgr.`notification_id`
                INNER JOIN `:db`.`host` h ON h.`host_id` = hsr.`host_host_id`
            WHERE n.`is_activated` = 1
            SQL;
    }
}
