<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace ConfigGenerateRemote;

use Exception;
use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use PDOStatement;
use Pimple\Container;

/**
 * Class
 *
 * @class HostCategory
 * @package ConfigGenerateRemote
 */
class HostCategory extends AbstractObject
{
    /** @var int */
    private $useCache = 1;
    /** @var int */
    private $doneCache = 0;

    /** @var array */
    private $hostSeverityCache = [];
    /** @var array */
    private $hostLinkedCache = [];

    /** @var string */
    protected $table = 'hostcategories';
    /** @var string */
    protected $generateFilename = 'hostcategories.infile';
    /** @var PDOStatement|null */
    protected $stmtHost = null;
    /** @var PDOStatement|null */
    protected $stmtHcName = null;

    /** @var string[] */
    protected $attributesWrite = [
        'hc_id',
        'hc_name',
        'hc_alias',
        'level',
        'icon_id',
    ];

    /**
     * HostCategory constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * Build cache of host severity
     *
     * @return void
     */
    private function cacheHostSeverity(): void
    {
        $stmt = $this->backendInstance->db->prepare(
            "SELECT hc_name, hc_alias, hc_id, level, icon_id
            FROM hostcategories
            WHERE level IS NOT NULL AND hc_activate = '1'"
        );

        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($values as &$value) {
            $this->hostSeverityCache[$value['hc_id']] = &$value;
        }
    }

    /**
     * Build cache of relations between host and severities
     *
     * @return void
     */
    private function cacheHostSeverityLinked(): void
    {
        $stmt = $this->backendInstance->db->prepare(
            'SELECT hc_id, host_host_id ' .
            'FROM hostcategories, hostcategories_relation ' .
            'WHERE level IS NOT NULL ' .
            'AND hc_activate = "1" ' .
            'AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id'
        );

        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->hostLinkedCache[$value['host_host_id']])) {
                if ($this->hostSeverityCache[$value['hc_id']]['level'] <
                    $this->hostSeverityCache[$this->hostLinkedCache[$value['host_host_id']]]
                ) {
                    $this->hostLinkedCache[$value['host_host_id']] = $value['hc_id'];
                }
            } else {
                $this->hostLinkedCache[$value['host_host_id']] = $value['hc_id'];
            }
        }
    }

    /**
     * Get host severity by host id
     *
     * @param int $hostId
     *
     * @return array|null
     * @throws Exception
     */
    public function getHostSeverityByHostId(int $hostId)
    {
        // Get from the cache
        if (isset($this->hostLinkedCache[$hostId])) {
            if (!$this->checkGenerate($this->hostLinkedCache[$hostId])) {
                $this->generateObjectInFile(
                    $this->hostSeverityCache[$this->hostLinkedCache[$hostId]],
                    $this->hostLinkedCache[$hostId]
                );
                Media::getInstance($this->dependencyInjector)
                    ->getMediaPathFromId($this->hostSeverityCache[$this->hostLinkedCache[$hostId]]['icon_id']);
            }
            return $this->hostLinkedCache[$hostId];
        }
        if ($this->doneCache == 1) {
            return null;
        }

        // We get unitary
        if (is_null($this->stmtHost)) {
            $this->stmtHost = $this->backendInstance->db->prepare(
                "SELECT hc_id, hc_name, hc_alias, level, icon_id
                FROM hostcategories_relation, hostcategories
                WHERE hostcategories_relation.host_host_id = :host_id
                    AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id
                    AND level IS NOT NULL AND hc_activate = '1'
                ORDER BY level DESC
                LIMIT 1"
            );
        }

        $this->stmtHost->bindParam(':host_id', $hostId, PDO::PARAM_INT);
        $this->stmtHost->execute();
        $severity = array_pop($this->stmtHost->fetchAll(PDO::FETCH_ASSOC));
        if (is_null($severity)) {
            $this->hostLinkedCache[$hostId] = null;
            return null;
        }
        $this->hostLinkedCache[$hostId] = $severity['hc_id'];
        $this->hostSeverityCache[$severity['hc_id']] = &$severity;

        $this->generateObjectInFile($severity, $severity['hc_id']);
        Media::getInstance($this->dependencyInjector)
            ->getMediaPathFromId($this->hostSeverityCache[$this->hostLinkedCache[$hostId]]['icon_id']);
        return $severity['hc_id'];
    }

    /**
     * Get host severity by id
     *
     * @param null|int $hcId
     * @return array|null
     */
    public function getHostSeverityById(?int $hcId)
    {
        if (is_null($hcId)) {
            return null;
        }
        if (!isset($this->hostSeverityCache[$hcId])) {
            return null;
        }

        return $this->hostSeverityCache[$hcId];
    }

    /**
     * Build cache
     *
     * @return void
     */
    private function buildCache()
    {
        if ($this->doneCache == 1) {
            return 0;
        }

        $this->cacheHostSeverity();
        $this->cacheHostSeverityLinked();
        $this->doneCache = 1;
    }
}
