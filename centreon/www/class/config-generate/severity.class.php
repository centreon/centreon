<?php

/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use Pimple\Container;

/**
 * Class
 *
 * @class Severity
 */
class Severity extends AbstractObject
{
    /** @var int */
    private $use_cache = 1;
    /** @var int */
    private $done_cache = 0;

    /** @var array */
    private $service_severity_cache = [];
    /** @var array */
    private $service_severity_by_name_cache = [];
    /** @var array */
    private $service_linked_cache = [];

    /** @var array */
    private $host_severity_cache = [];
    /** @var array */
    private $host_linked_cache = [];
    /** @var array */
    private $host_severities = [];
    /** @var array */
    private $service_severities = [];

    /** @var null */
    protected $stmt_host = null;
    /** @var null */
    protected $stmt_service = null;
    /** @var null */
    protected $stmt_hc_name = null;
    /** @var string */
    protected $generate_filename =  'severities.cfg';
    /** @var string */
    protected string $object_name = 'severity';
    /** @var string[] */
    protected $attributesSelectHost = [
        'hc_id' => 'id',
        'hc_name' => 'severity_name',
        'level' => 'level',
        'icon_id' => 'icon_id',
    ];
    /** @var string[] */
    protected $attributesSelectService = [
        'sc_id' => 'id',
        'sc_name' => 'severity_name',
        'level' => 'level',
        'icon_id' => 'icon_id',
    ];
    /** @var string[] */
    protected $attributes_write = [
        'id',
        'severity_name',
        'level',
        'icon_id',
        'type',
    ];

    /**
     * Severity constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function cacheHostSeverity(): void
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT hc_name, hc_id, level, icon_id
            FROM hostcategories
            WHERE level IS NOT NULL AND hc_activate = '1'"
        );

        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($values as &$value) {
            $this->host_severity_cache[$value['hc_id']] = &$value;
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function cacheHostSeverityLinked(): void
    {
        $stmt = $this->backend_instance->db->prepare(
            'SELECT hc_id, host_host_id ' .
            'FROM hostcategories, hostcategories_relation ' .
            'WHERE level IS NOT NULL ' .
            'AND hc_activate = "1" ' .
            'AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id'
        );

        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->host_linked_cache[$value['host_host_id']])) {
                if (
                    $this->host_severity_cache[$value['hc_id']]['level']
                    < $this->host_severity_cache[$this->host_linked_cache[$value['host_host_id']]]
                ) {
                    $this->host_linked_cache[$value['host_host_id']] = $value['hc_id'];
                }
            } else {
                $this->host_linked_cache[$value['host_host_id']] = $value['hc_id'];
            }
        }
    }

    /**
     * @param $host_id
     *
     * @return mixed|string|null
     * @throws PDOException
     */
    public function getHostSeverityByHostId($host_id)
    {
        # Get from the cache
        if (isset($this->host_linked_cache[$host_id])) {
            return $this->host_linked_cache[$host_id];
        }
        if ($this->done_cache == 1) {
            return null;
        }

        # We get unitary
        if (is_null($this->stmt_host)) {
            $this->stmt_host = $this->backend_instance->db->prepare(
                "SELECT hc_id, hc_name, level
                FROM hostcategories_relation, hostcategories
                WHERE hostcategories_relation.host_host_id = :host_id
                    AND hostcategories_relation.hostcategories_hc_id = hostcategories.hc_id
                    AND level IS NOT NULL AND hc_activate = '1'
                ORDER BY level DESC
                LIMIT 1"
            );
        }

        $this->stmt_host->bindParam(':host_id', $host_id, PDO::PARAM_INT);
        $this->stmt_host->execute();
        $hostsCategories = $this->stmt_host->fetchAll(PDO::FETCH_ASSOC);
        $severity = array_pop($hostsCategories);
        if (is_null($severity)) {
            $this->host_linked_cache[$host_id] = null;
            return null;
        }
        $this->host_linked_cache[$host_id] = $severity['hc_id'];
        $this->host_severity_cache[$severity['hc_id']] = &$severity;
        return $severity['hc_id'];
    }

    /**
     * @param $hc_id
     *
     * @return mixed|null
     */
    public function getHostSeverityById($hc_id)
    {
        if (is_null($hc_id)) {
            return null;
        }
        if (!isset($this->host_severity_cache[$hc_id])) {
            return null;
        }

        $this->host_severities[$hc_id] = $this->host_severity_cache[$hc_id];
        return $this->host_severity_cache[$hc_id];
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function cacheServiceSeverity(): void
    {
        $stmt = $this->backend_instance->db->prepare(
            "SELECT sc_name, sc_id, level, icon_id
            FROM service_categories
            WHERE level IS NOT NULL AND sc_activate = '1'"
        );

        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($values as &$value) {
            $this->service_severity_by_name_cache[$value['sc_name']] = &$value;
            $this->service_severity_cache[$value['sc_id']] = &$value;
        }
    }

    /**
     * @return void
     * @throws PDOException
     */
    private function cacheServiceSeverityLinked(): void
    {
        $stmt = $this->backend_instance->db->prepare(
            'SELECT service_categories.sc_id, service_service_id ' .
            'FROM service_categories, service_categories_relation ' .
            'WHERE level IS NOT NULL ' .
            'AND sc_activate = "1" ' .
            'AND service_categories_relation.sc_id = service_categories.sc_id'
        );

        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $value) {
            if (isset($this->service_linked_cache[$value['service_service_id']])) {
                if (
                    $this->service_severity_cache[$value['sc_id']]['level']
                    < $this->service_severity_cache[$this->service_linked_cache[$value['service_service_id']]]
                ) {
                    $this->service_linked_cache[$value['service_service_id']] = $value['sc_id'];
                }
            } else {
                $this->service_linked_cache[$value['service_service_id']] = $value['sc_id'];
            }
        }
    }

    /**
     * @return int|void
     * @throws PDOException
     */
    private function buildCache()
    {
        if ($this->done_cache == 1) {
            return 0;
        }

        $this->cacheHostSeverity();
        $this->cacheHostSeverityLinked();
        $this->cacheServiceSeverity();
        $this->cacheServiceSeverityLinked();
        $this->done_cache = 1;
    }

    /**
     * @param $service_id
     *
     * @return mixed|string|null
     * @throws PDOException
     */
    public function getServiceSeverityByServiceId($service_id)
    {
        # Get from the cache
        if (isset($this->service_linked_cache[$service_id])) {
            return $this->service_linked_cache[$service_id];
        }
        if ($this->done_cache == 1) {
            return null;
        }

        # We get unitary
        if (is_null($this->stmt_service)) {
            $this->stmt_service = $this->backend_instance->db->prepare(
                "SELECT service_categories.sc_id, sc_name, level
                FROM service_categories_relation, service_categories
                WHERE service_categories_relation.service_service_id = :service_id
                    AND service_categories_relation.sc_id = service_categories.sc_id
                    AND level IS NOT NULL AND sc_activate = '1'
                ORDER BY level DESC
                LIMIT 1"
            );
        }

        $this->stmt_service->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $this->stmt_service->execute();
        $serviceCategories = $this->stmt_service->fetchAll(PDO::FETCH_ASSOC);
        $severity = array_pop($serviceCategories);
        if (is_null($severity)) {
            $this->service_linked_cache[$service_id] = null;
            return null;
        }

        $this->service_linked_cache[$service_id] = $severity['sc_id'];
        $this->service_severity_by_name_cache[$severity['sc_name']] = &$severity;
        $this->service_severity_cache[$severity['sc_id']] = &$severity;
        return $severity['sc_id'];
    }

    /**
     * @param $sc_id
     *
     * @return mixed|null
     */
    public function getServiceSeverityById($sc_id)
    {
        if (is_null($sc_id)) {
            return null;
        }
        if (!isset($this->service_severity_cache[$sc_id])) {
            return null;
        }

        $this->service_severities[$sc_id] = $this->service_severity_cache[$sc_id];
        return $this->service_severity_cache[$sc_id];
    }

    /**
     * @param $hc_name
     *
     * @return mixed|null
     * @throws PDOException
     */
    public function getServiceSeverityMappingHostSeverityByName($hc_name)
    {
        if (isset($this->service_severity_by_name_cache[$hc_name])) {
            $this->service_severities[$this->service_severity_by_name_cache[$hc_name]['sc_id']] = $this->service_severity_by_name_cache[$hc_name];
            return $this->service_severity_by_name_cache[$hc_name];
        }
        if ($this->done_cache == 1) {
            return null;
        }

        # We get unitary
        if (is_null($this->stmt_hc_name)) {
            $this->stmt_hc_name = $this->backend_instance->db->prepare(
                "SELECT sc_name, sc_id, level
                FROM service_categories
                WHERE sc_name = :sc_name AND level IS NOT NULL AND sc_activate = '1'"
            );
        }

        $this->stmt_hc_name->bindParam(':sc_name', $hc_name, PDO::PARAM_STR);
        $this->stmt_hc_name->execute();
        $serviceCategories = $this->stmt_hc_name->fetchAll(PDO::FETCH_ASSOC);
        $severity = array_pop($serviceCategories);
        if (is_null($severity)) {
            $this->service_severity_by_name_cache[$hc_name] = null;
            return null;
        }

        $this->service_severity_by_name_cache[$hc_name] = &$severity;
        $this->service_severity_cache[$severity['sc_id']] = &$severity;
        $this->service_severities[$severity['sc_id']] = $this->service_severity_cache[$severity['sc_id']];
        return $severity;
    }

    /**
     * Export cached objects in corresponding export file
     */
    public function generateObjects(): void
    {
        $this->generateServiceSeverityObjects();

        $this->generateHostSeverityObjects();
    }

    /**
     * Export service severities in corresponding export file
     */
    private function generateServiceSeverityObjects(): void
    {
        foreach ($this->service_severities as $id => $value) {
            if (is_null($value)) {
                continue;
            }
            $severity = ['type' => 'service'];
            foreach ($this->attributesSelectService as $selectAttr => $writeAttr) {
                $severity[$writeAttr] = $value[$selectAttr];
            }
            $this->generateObjectInFile($severity, $id);
        }
    }

    /**
     * Export host severities in corresponding export file
     */
    private function generateHostSeverityObjects(): void
    {
        foreach ($this->host_severities as $id => $value) {
            if (is_null($value)) {
                continue;
            }
            $severity = ['type' => 'host'];
            foreach ($this->attributesSelectHost as $selectAttr => $writeAttr) {
                $severity[$writeAttr] = $value[$selectAttr];
            }
            $this->generateObjectInFile($severity, $id);
        }
    }

    /**
     * Reset instance
     */
    public function reset(): void
    {
        $this->host_severities = [];
        $this->service_severities = [];
        parent::reset();
    }
}
