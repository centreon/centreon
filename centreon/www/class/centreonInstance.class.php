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

/**
 * Class
 *
 * @class CentreonInstance
 * @description Class for handling Instances
 */
class CentreonInstance
{
    /** @var array */
    public $paramsByName;
    /** @var CentreonDB */ // FIXME three db connections ??
    public $DB;
    /** @var CentreonDB */
    protected $db;
    /** @var CentreonDB */
    protected $dbo;
    /** @var array */
    protected $params;
    /** @var array */
    protected $instances;
    /** @var CentreonInstance|null $staticInstance */
    private static ?CentreonInstance $staticInstance = null;

    /**
     * CentreonInstance constructor
     *
     * @param CentreonDB $db
     * @param CentreonDB|null $dbo
     *
     * @throws PDOException
     */
    public function __construct($db, $dbo = null)
    {
        $this->db = $db;
        if (!empty($dbo)) {
            $this->dbo = $dbo;
        }
        $this->instances = [];
        $this->initParams();
    }

    /**
     * @param CentreonDB $db
     * @param CentreonDB|null $dbo
     *
     * @return CentreonInstance
     * @throws PDOException
     */
    public static function getInstance(CentreonDB $db, ?CentreonDB $dbo = null): CentreonInstance
    {
        return self::$staticInstance ??= new self($db, $dbo);
    }

    /**
     * Initialize Parameters
     *
     * @return void
     * @throws PDOException
     */
    protected function initParams()
    {
        $this->params = [];
        $this->paramsByName = [];
        $query = "SELECT id, name, localhost, last_restart, ns_ip_address FROM nagios_server";
        $res = $this->db->query($query);
        while ($row = $res->fetchRow()) {
            $instanceId = $row['id'];
            $instanceName = $row['name'];
            $this->instances[$instanceId] = $instanceName;
            $this->params[$instanceId] = [];
            $this->paramsByName[$instanceName] = [];
            foreach ($row as $key => $value) {
                $this->params[$instanceId][$key] = $value;
                $this->paramsByName[$instanceName][$key] = $value;
            }
        }
    }

    /**
     * Returns a filtered array with only integer ids
     *
     * @param  int[] $ids
     * @return int[] filtered
     */
    private function filteredArrayId(array $ids): array
    {
        return array_filter($ids, function ($id) {
            return is_numeric($id);
        });
    }

    /**
     * Get instance_id and name from instances ids
     *
     * @param int[] $pollerIds
     *
     * @return array $pollers [['instance_id => integer, 'name' => string],...]
     * @throws PDOException
     */
    public function getInstancesMonitoring($pollerIds = [])
    {
        $pollers = [];

        if (!empty($pollerIds)) {
            /* checking here that the array provided as parameter
             * is exclusively made of integers (servicegroup ids)
             */
            $filteredPollerIds = $this->filteredArrayId($pollerIds);
            $pollerParams = [];
            if ($filteredPollerIds !== []) {
                /*
                 * Building the pollerParams hash table in order to correctly
                 * bind ids as ints for the request.
                 */
                foreach ($filteredPollerIds as $index => $filteredPollerId) {
                    $pollerParams[':pollerId' . $index] = $filteredPollerId;
                }
                $stmt = $this->DB->prepare( // FIXME I think it's $db, to check
                    'SELECT i.instance_id, i.name FROM instances i ' .
                    'WHERE i.instance_id IN ( ' . implode(',', array_keys($pollerParams)) . ' )'
                );
                foreach ($pollerParams as $index => $value) {
                    $stmt->bindValue($index, $value, \PDO::PARAM_INT);
                }
                $stmt->execute();

                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $pollers[] = [
                        'id' => $row['instance_id'],
                        'name' => $row['name']
                    ];
                }
            }
        }

        return $pollers;
    }

    /**
     * Get Parameter
     *
     * @param mixed $instance
     * @param string $paramName
     * @return string
     */
    public function getParam($instance, $paramName)
    {
        if (is_numeric($instance)) {
            if (isset($this->params[$instance]) && isset($this->params[$instance][$paramName])) {
                return $this->params[$instance][$paramName];
            }
        } elseif (isset($this->paramsByName[$instance]) && isset($this->paramsByName[$instance][$paramName])) {
            return $this->paramsByName[$instance][$paramName];
        }
        return null;
    }

    /**
     * Get Instances
     *
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * Get command data from poller id
     *
     * @param int $pollerId
     *
     * @return array
     * @throws PDOException
     */
    public function getCommandData($pollerId)
    {
        $sql = "SELECT c.command_id, c.command_name, c.command_line 
            FROM command c, poller_command_relations pcr
            WHERE pcr.poller_id = ?
            AND pcr.command_id = c.command_id
            ORDER BY pcr.command_order";
        $res = $this->db->prepare($sql);
        $res->execute([$pollerId]);
        $arr = [];
        while ($row = $res->fetchRow()) {
            $arr[] = $row;
        }
        return $arr;
    }

    /**
     * Return list of commands used by poller
     *
     * @param int|null $pollerId
     *
     * @return array
     * @throws PDOException
     */
    public function getCommandsFromPollerId($pollerId = null)
    {
        $arr = [];
        $i = 0;
        if (!isset($_REQUEST['pollercmd']) && $pollerId) {
            $sql = "SELECT command_id 
                FROM poller_command_relations 
                WHERE poller_id = ?
                ORDER BY command_order";
            $res = $this->db->prepare($sql);
            $res->execute([$pollerId]);
            while ($row = $res->fetchRow()) {
                $arr[$i]['pollercmd_#index#'] = $row['command_id'];
                $i++;
            }
        } elseif (isset($_REQUEST['pollercmd'])) {
            foreach ($_REQUEST['pollercmd'] as $val) {
                $arr[$i]['pollercmd_#index#'] = $val;
                $i++;
            }
        }
        return $arr;
    }

    /**
     * Set post-restart commands
     *
     * @param int $pollerId
     * @param array $commands
     *
     * @return void
     * @throws PDOException
     */
    public function setCommands($pollerId, $commands): void
    {
        $this->db->query("DELETE FROM poller_command_relations
                WHERE poller_id = " . $this->db->escape($pollerId));

        $stored = [];
        $i = 1;
        foreach ($commands as $value) {
            if ($value != "" &&
                !isset($stored[$value])
            ) {
                $this->db->query("INSERT INTO poller_command_relations
                        (`poller_id`, `command_id`, `command_order`) 
                        VALUES (" . $this->db->escape($pollerId) . ", " . $this->db->escape($value) . ", " . $i . ")");
                $stored[$value] = true;
                $i++;
            }
        }
    }

    /**
     * @param array $values
     * @param array $options
     *
     * @return array
     * @throws PDOException
     */
    public function getObjectForSelect2($values = [], $options = [])
    {
        global $centreon;

        $selectedInstances = '';
        $items = [];

        if (empty($values)) {
            return $items;
        }

        // get list of authorized pollers
        if (!$centreon->user->access->admin) {
            $pollerAcl = $centreon->user->access->getPollers();
        }

        $listValues = '';
        $queryValues = [];
        foreach ($values as $k => $v) {
            $multipleValues = explode(',', $v);
            foreach ($multipleValues as $item) {
                $listValues .= ':pId_' . $item . ', ';
                $queryValues['pId_' . $item] = (int)$item;
            }
        }
        $listValues = rtrim($listValues, ', ');
        $selectedInstances .= " AND rel.instance_id IN ($listValues) ";

        $query = 'SELECT DISTINCT p.name as name, p.id  as id FROM cfg_resource r, nagios_server p, ' .
            'cfg_resource_instance_relations rel ' .
            ' WHERE r.resource_id = rel.resource_id' .
            ' AND p.id = rel.instance_id ' .
            ' AND p.id IN (' . $listValues . ')' . $selectedInstances .
            ' ORDER BY p.name';

        $stmt = $this->db->prepare($query);
        foreach ($queryValues as $key => $id) {
            $stmt->bindValue(':' . $key, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        while ($data = $stmt->fetch()) {
            $hide = false;
            if (
                ! $centreon->user->access->admin
                && count($pollerAcl)
                && !in_array($data['id'], array_keys($pollerAcl))
            ) {
                $hide = true;
            }
            $items[] = [
                'id' => $data['id'],
                'text' => HtmlSanitizer::createFromString($data['name'])->sanitize()->getString(),
                'hide' => $hide
            ];
        }

        return $items;
    }

    /**
     * @param string $instanceName
     *
     * @return array
     * @throws PDOException
     */
    public function getHostsByInstance($instanceName)
    {
        $instanceList = [];

        $query = "SELECT host_name, name " .
            " FROM host h, nagios_server ns, ns_host_relation nshr " .
            " WHERE ns.name = '" . $this->db->escape($instanceName) . "'" .
            " AND nshr.host_host_id = h.host_id " .
            " AND h.host_activate = '1' " .
            " ORDER BY h.host_name";
        $result = $this->db->query($query);

        while ($elem = $result->fetchrow()) {
            $instanceList[] = ['host' => $elem['host_name'], 'name' => $instanceName];
        }
        return $instanceList;
    }

    /**
     * @param string $instanceName
     *
     * @return mixed
     * @throws PDOException
     */
    public function getInstanceId($instanceName)
    {
        $query = "SELECT ns.id " .
            " FROM nagios_server ns " .
            " WHERE ns.name = '" . $this->db->escape($instanceName) . "'";
        $result = $this->db->query($query);

        return $result->fetchrow();
    }
}
