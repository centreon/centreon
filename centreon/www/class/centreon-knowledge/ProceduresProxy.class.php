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

require_once _CENTREON_PATH_ . '/www/include/configuration/configKnowledge/functions.php';
require_once _CENTREON_PATH_ . '/www/class/centreonHost.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonService.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreon-knowledge/wikiApi.class.php';

/**
 * Class
 *
 * @class ProceduresProxy
 */
class ProceduresProxy
{
    /** @var procedures */
    public $proc;

    /** @var CentreonDB */
    private $DB;

    /** @var mixed */
    private $wikiUrl;

    /** @var CentreonHost */
    private $hostObj;

    /** @var CentreonService */
    private $serviceObj;

    /**
     * ProceduresProxy constructor
     *
     * @param CentreonDB $pearDB
     *
     * @throws PDOException
     */
    public function __construct($pearDB)
    {
        $this->DB = $pearDB;
        $this->hostObj = new CentreonHost($this->DB);
        $this->serviceObj = new CentreonService($this->DB);

        $conf = getWikiConfig($this->DB);
        $this->wikiUrl = $conf['kb_wiki_url'];
        $this->proc = new procedures($this->DB);
    }

    /**
     * @param string $hostName
     *
     * @throws PDOException
     * @return int
     */
    private function getHostId($hostName)
    {
        $statement = $this->DB->prepare('SELECT host_id FROM host WHERE host_name LIKE :hostName');
        $statement->bindValue(':hostName', $hostName, PDO::PARAM_STR);
        $statement->execute();
        $hostId = 0;
        if ($row = $statement->fetch()) {
            $hostId = $row['host_id'];
        }

        return $hostId;
    }

    /**
     * Get service id from hostname and service description
     *
     * @param string $hostName
     * @param string $serviceDescription
     *
     * @throws PDOException
     * @return int|null
     */
    private function getServiceId($hostName, $serviceDescription): ?int
    {
        // Get Services attached to hosts
        $statement = $this->DB->prepare(
            'SELECT s.service_id FROM host h, service s, host_service_relation hsr '
            . 'WHERE hsr.host_host_id = h.host_id '
            . 'AND hsr.service_service_id = service_id '
            . 'AND h.host_name LIKE :hostName '
            . 'AND s.service_description LIKE :serviceDescription '
        );
        $statement->bindValue(':hostName', $hostName, PDO::PARAM_STR);
        $statement->bindValue(':serviceDescription', $serviceDescription, PDO::PARAM_STR);
        $statement->execute();
        if ($row = $statement->fetch()) {
            return (int) $row['service_id'];
        }
        $statement->closeCursor();

        // Get Services attached to hostgroups
        $statement = $this->DB->prepare(
            'SELECT s.service_id FROM hostgroup_relation hgr, host h, service s, host_service_relation hsr '
            . 'WHERE hgr.host_host_id = h.host_id '
            . 'AND hsr.hostgroup_hg_id = hgr.hostgroup_hg_id '
            . 'AND h.host_name LIKE :hostName '
            . 'AND service_id = hsr.service_service_id '
            . 'AND service_description LIKE :serviceDescription '
        );
        $statement->bindValue(':hostName', $hostName, PDO::PARAM_STR);
        $statement->bindValue(':serviceDescription', $serviceDescription, PDO::PARAM_STR);
        $statement->execute();
        if ($row = $statement->fetch()) {
            return (int) $row['service_id'];
        }
        $statement->closeCursor();

        return null;
    }

    /**
     * Get service notes url
     *
     * @param int $serviceId
     *
     * @throws PDOException
     * @return string|null
     */
    private function getServiceNotesUrl(int $serviceId): ?string
    {
        $notesUrl = null;

        $statement = $this->DB->prepare(
            'SELECT esi_notes_url '
            . 'FROM extended_service_information '
            . 'WHERE service_service_id = :serviceId'
        );

        $statement->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
        $statement->execute();

        if ($row = $statement->fetch()) {
            $notesUrl = $row['esi_notes_url'];
        }

        return $notesUrl;
    }

    /**
     * Get host url
     *
     * @param string $hostName
     *
     * @throws PDOException
     * @return string|null
     */
    public function getHostUrl($hostName): ?string
    {
        $hostId = $this->getHostId($hostName);

        if ($hostId === null) {
            return null;
        }

        $hostProperties = $this->hostObj->getInheritedValues(
            $hostId,
            [],
            1,
            ['host_name', 'ehi_notes_url']
        );

        if (isset($hostProperties['ehi_notes_url'])) {
            return $this->wikiUrl . '/index.php?title=Host_:_' . $hostProperties['host_name'];
        }

        $templates = $this->hostObj->getTemplateChain($hostId);
        foreach ($templates as $template) {
            $inheritedHostProperties = $this->hostObj->getInheritedValues(
                $template['id'],
                [],
                1,
                ['host_name', 'ehi_notes_url']
            );

            if (isset($inheritedHostProperties['ehi_notes_url'])) {
                return $this->wikiUrl . '/index.php?title=Host-Template_:_' . $inheritedHostProperties['host_name'];
            }
        }

        return null;
    }

    /**
     * Get service url
     *
     * @param string $hostName
     * @param string $serviceDescription
     *
     * @throws PDOException
     * @return string|null
     */
    public function getServiceUrl($hostName, $serviceDescription): ?string
    {
        $serviceDescription = str_replace(' ', '_', $serviceDescription);

        $serviceId = $this->getServiceId($hostName, $serviceDescription);

        if ($serviceId === null) {
            return null;
        }

        // Check Service
        $notesUrl = $this->getServiceNotesUrl($serviceId);
        if ($notesUrl !== null) {
            return $this->wikiUrl . '/index.php?title=Service_:_' . $hostName . '_/_' . $serviceDescription;
        }

        // Check service Template
        $serviceId = $this->getServiceId($hostName, $serviceDescription);
        $templates = $this->serviceObj->getTemplatesChain($serviceId);
        foreach (array_reverse($templates) as $templateId) {
            $templateDescription = $this->serviceObj->getServiceDesc($templateId);
            $notesUrl = $this->getServiceNotesUrl((int) $templateId);
            if ($notesUrl !== null) {
                return $this->wikiUrl . '/index.php?title=Service-Template_:_' . $templateDescription;
            }
        }

        return $this->getHostUrl($hostName);
    }
}
