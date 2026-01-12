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

require_once 'Centreon/Object/ObjectRt.php';

/**
 * Class
 *
 * @class Centreon_Object_RtAcknowledgement
 */
class Centreon_Object_RtAcknowledgement extends Centreon_ObjectRt
{
    /** @var string */
    protected $table = 'acknowledgements';

    /** @var string */
    protected $primaryKey = 'acknowledgement_id';

    /** @var string */
    protected $uniqueLabelField = 'comment_data';

    /**
     * @param int[] $hostIds
     * @return array
     */
    public function getLastHostAcknowledgement($hostIds = [])
    {
        $hostFilter = '';
        if (! empty($hostIds)) {
            $hostFilter = 'AND hosts.host_id IN (' . implode(',', $hostIds) . ')';
        }

        return $this->getResult(
            sprintf(
                'SELECT  ack.acknowledgement_id, hosts.name, ack.entry_time as entry_time,
                    ack.author, ack.comment_data, ack.sticky, ack.notify_contacts, ack.persistent_comment
                FROM acknowledgements ack
                INNER JOIN hosts
                    ON hosts.host_id = ack.host_id
                INNER JOIN
                    (SELECT MAX(ack.entry_time) AS entry_time, ack.host_id
                    FROM acknowledgements ack
                    INNER JOIN hosts
                        ON hosts.host_id = ack.host_id
                    WHERE hosts.acknowledged = 1
                    AND ack.service_id = 0
                    %s
                    GROUP BY ack.host_id
                    ) AS tmp
                    ON tmp.entry_time = ack.entry_time
                    AND tmp.host_id = ack.host_id
                    AND ack.service_id = 0
                ORDER BY ack.entry_time, hosts.name',
                $hostFilter
            )
        );
    }

    /**
     * @param string[] $svcList
     * @return array
     */
    public function getLastSvcAcknowledgement($svcList = [])
    {
        $serviceFilter = '';

        if (! empty($svcList)) {
            $serviceFilter = 'AND (';
            $filterTab = [];
            $counter = count($svcList);
            for ($i = 0; $i < $counter; $i += 2) {
                $hostname = $svcList[$i];
                $serviceDescription = $svcList[$i + 1];
                $filterTab[] = '(host.name = "'
                    . $hostname
                    . '" AND service.description = "'
                    . $serviceDescription
                    . '")';
            }
            $serviceFilter .= implode(' AND ', $filterTab) . ') ';
        }

        return $this->getResult(
            sprintf(
                'SELECT ack.acknowledgement_id, host.name, service.description, ack.entry_time,
                       ack.author, ack.comment_data , ack.sticky, ack.notify_contacts, ack.persistent_comment
                FROM acknowledgements ack
                INNER JOIN services service
                    ON service.service_id = ack.service_id
                INNER JOIN hosts host
                    ON host.host_id = service.host_id
                    AND host.host_id = ack.host_id
                INNER JOIN
                    (SELECT max(ack.entry_time) AS entry_time, host.host_id, service.service_id
                    FROM acknowledgements ack
                    INNER JOIN services service
                        ON service.service_id = ack.service_id
                    INNER JOIN hosts host
                        ON host.host_id = service.host_id
                        AND host.host_id = ack.host_id
                    WHERE service.acknowledged = 1
                    %s
                    GROUP BY host.host_id, service.service_id) AS tmp
                    ON tmp.entry_time = ack.entry_time
                    AND tmp.host_id = ack.host_id
                    AND tmp.service_id = ack.service_id
                ORDER BY ack.entry_time, host.name, service.description',
                $serviceFilter
            )
        );
    }

    /**
     * @param $serviceId
     * @return bool
     */
    public function svcIsAcknowledged($serviceId)
    {
        $query = 'SELECT acknowledged FROM services WHERE service_id = ? ';

        return (bool) ($this->getResult($query, [$serviceId], 'fetch')['acknowledged'] == 1);
    }

    /**
     * @param $hostId
     * @return bool
     */
    public function hostIsAcknowledged($hostId)
    {
        $query = 'SELECT acknowledged FROM hosts WHERE host_id = ? ';

        return (bool) ($this->getResult($query, [$hostId], 'fetch')['acknowledged'] == 1);
    }
}
