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

namespace CentreonClapi;

use Centreon_Object_Configuration_Ldap;
use Centreon_Object_Contact;
use Centreon_Object_Contact_Group;
use Centreon_Object_Ldap;
use Centreon_Object_Server_Ldap;
use Exception;
use PDO;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once 'centreonContact.class.php';
require_once 'Centreon/Object/Ldap/ConfigurationLdap.php';
require_once 'Centreon/Object/Ldap/ObjectLdap.php';
require_once 'Centreon/Object/Ldap/ServerLdap.php';

/**
 * Class
 *
 * @class CentreonLDAP
 * @package CentreonClapi
 * @description Class for managing ldap servers
 */
class CentreonLDAP extends CentreonObject
{
    public const NB_ADD_PARAM = 2;
    public const AR_NOT_EXIST = 'LDAP configuration ID not found';

    /** @var array|string[] */
    public array $aDepends = [
        'CG',
        'CONTACTTPL',
    ];

    /** @var array<string,string> */
    protected array $baseParams = [
        'alias' => '',
        'bind_dn' => '',
        'bind_pass' => '',
        'group_base_search' => '',
        'group_filter' => '',
        'group_member' => '',
        'group_name' => '',
        'ldap_auto_import' => '',
        'ldap_contact_tmpl' => '',
        'ldap_default_cg' => '',
        'ldap_dns_use_domain' => '',
        'ldap_connection_timeout' => '',
        'ldap_search_limit' => '',
        'ldap_search_timeout' => '',
        'ldap_srv_dns' => '',
        'ldap_store_password' => '',
        'ldap_template' => '',
        'protocol_version' => '',
        'user_base_search' => '',
        'user_email' => '',
        'user_filter' => '',
        'user_firstname' => '',
        'user_lastname' => '',
        'user_name' => '',
        'user_pager' => '',
        'user_group' => '',
        'ldap_auto_sync' => '',
        'ldap_sync_interval' => '',
    ];

    /** @var string[] */
    protected array $serverParams = ['host_address', 'host_port', 'host_order', 'use_ssl', 'use_tls'];

    /**
     * CentreonLDAP constructor.
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Ldap($dependencyInjector);
        $this->action = 'LDAP';
    }

    /**
     * Get Ldap Configuration Id.
     *
     * @param string $name
     *
     * @return mixed returns null if no ldap id is found
     * @throws PDOException
     */
    public function getLdapId($name)
    {
        $res = $this->db->prepare(
            <<<'SQL'
                SELECT ar_id
                FROM auth_ressource
                WHERE ar_name = :name
                SQL
        );
        $res->bindValue(':name', $name, PDO::PARAM_STR);
        $res->execute();

        $row = $res->fetch();
        if (! isset($row['ar_id'])) {
            return;
        }
        $ldapId = $row['ar_id'];
        unset($res);

        return $ldapId;
    }

    /**
     * @param $id
     *
     * @return array|false
     * @throws PDOException
     */
    public function getLdapServers($id)
    {
        $res = $this->db->prepare(
            <<<'SQL'
                SELECT host_address, host_port
                FROM auth_ressource_host
                WHERE auth_ressource_id = :id
                SQL
        );
        $res->bindValue(':id', $id, PDO::PARAM_INT);
        $res->execute();

        return $res->fetchAll();
    }

    /**
     * @param array $params
     * @param array $filters
     *
     * @throws PDOException
     */
    public function show($params = [], $filters = []): void
    {
        $sql = 'SELECT ar_id, ar_name, ar_description, ar_enable
        	FROM auth_ressource
        	ORDER BY ar_name';
        $res = $this->db->query($sql);
        $row = $res->fetchAll();
        echo "id;name;description;status\n";
        foreach ($row as $ldap) {
            echo $ldap['ar_id'] . $this->delim
                . $ldap['ar_name'] . $this->delim
                . $ldap['ar_description'] . $this->delim
                . $ldap['ar_enable'] . "\n";
        }
    }

    /**
     * @param string|null $arName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function showserver($arName = null): void
    {
        if (! $arName) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $arId = $this->getLdapId($arName);
        if (is_null($arId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ' ' . $arName);
        }
        $sql = 'SELECT ldap_host_id, host_address, host_port, use_ssl, use_tls, host_order
                FROM auth_ressource_host
                WHERE auth_ressource_id = :auth_ressource_id
                ORDER BY host_order';
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':auth_ressource_id', (int) $arId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetchAll(PDO::FETCH_ASSOC);
        echo "id;address;port;ssl;tls;order\n";
        foreach ($row as $srv) {
            echo $srv['ldap_host_id'] . $this->delim
                . $srv['host_address'] . $this->delim
                . $srv['host_port'] . $this->delim
                . $srv['use_ssl'] . $this->delim
                . $srv['use_tls'] . $this->delim
                . $srv['host_order'] . "\n";
        }
    }

    /**
     * Add a new ldap configuration.
     *
     * @param string $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function add($parameters): void
    {
        if (! isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_ADD_PARAM) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        [$name, $description] = $params;
        if (! $this->isUnique($name)) {
            throw new CentreonClapiException(self::NAMEALREADYINUSE . ' (' . $name . ')');
        }
        $stmt = $this->db->prepare(
            "INSERT INTO auth_ressource (ar_name, ar_description, ar_enable, ar_type)
            VALUES (:arName, :description, '1', 'ldap')"
        );
        $stmt->bindValue(':arName', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Add server to ldap configuration.
     *
     * @param string $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function addserver($parameters): void
    {
        if (! isset($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < 5) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        [$arName, $address, $port, $ssl, $tls] = $params;
        if (! is_numeric($port)) {
            throw new CentreonClapiException('Incorrect port parameters');
        }
        if (! is_numeric($ssl)) {
            throw new CentreonClapiException('Incorrect ssl parameters');
        }
        if (! is_numeric($tls)) {
            throw new CentreonClapiException('Incorrect tls parameters');
        }

        $arId = $this->getLdapId($arName);

        if (is_null($arId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ' ' . $arName);
        }

        $serverList = $this->getLdapServers($arId);
        $newServer = ['host_address' => $address, 'host_port' => $port];
        if (in_array($newServer, $serverList, true)) {
            throw new CentreonClapiException(self::OBJECTALREADYEXISTS . ' ' . $address);
        }

        $statement = $this->db->prepare(
            <<<'SQL'
                INSERT INTO auth_ressource_host (auth_ressource_id, host_address, host_port, use_ssl, use_tls)
                VALUES (:arId, :address, :port, :ssl, :tls)
                SQL
        );

        $statement->bindValue(':arId', $arId, PDO::PARAM_INT);
        $statement->bindValue(':address', $address, PDO::PARAM_STR);
        $statement->bindValue(':port', $port, PDO::PARAM_INT);
        $statement->bindValue(':ssl', $ssl, PDO::PARAM_INT);
        $statement->bindValue(':tls', $tls, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Delete configuration.
     *
     * @param null|mixed $arName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function del($arName = null): void
    {
        if (! isset($arName)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $arId = $this->getLdapId($arName);
        if (is_null($arId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ' ' . $arName);
        }

        $statement = $this->db->prepare(
            <<<'SQL'
                DELETE FROM auth_ressource
                WHERE ar_id = :arId
                SQL
        );
        $statement->bindValue(':arId', $arId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param $serverId
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function delserver($serverId): void
    {
        if (! isset($serverId)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (! is_numeric($serverId)) {
            throw new CentreonClapiException('Incorrect server id parameters');
        }

        $statement = $this->db->prepare(
            <<<'SQL'
                DELETE FROM auth_ressource_host
                WHERE ldap_host_id = :serverId
                SQL
        );
        $statement->bindValue(':serverId', $serverId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Set parameters.
     *
     * @param array $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function setparam($parameters = []): void
    {
        if (empty($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $arId = $this->getLdapId($params[0]);
        if (is_null($arId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[0]);
        }
        if (in_array(mb_strtolower($params[1]), ['name', 'description', 'enable'], true)) {
            if (mb_strtolower($params[1]) === 'name') {
                if (! $this->isUnique($params[2], $arId)) {
                    throw new CentreonClapiException(self::NAMEALREADYINUSE . ' (' . $params[2] . ')');
                }
            }
            $statement = $this->db->prepare(
                <<<SQL
                    UPDATE auth_ressource
                    SET ar_{$params[1]} = :param
                    WHERE ar_id = :arId
                    SQL
            );
            $statement->bindValue(':param', $params[2], PDO::PARAM_STR);
            $statement->bindValue(':arId', $arId, PDO::PARAM_INT);
            $statement->execute();
        } elseif (isset($this->baseParams[mb_strtolower($params[1])])) {
            if (mb_strtolower($params[1]) === 'ldap_contact_tmpl') {
                if (empty($params[2])) {
                    throw new CentreonClapiException(self::MISSINGPARAMETER);
                }
                $contactObj = new CentreonContact($this->dependencyInjector);
                $params[2] = $contactObj->getContactID($params[2]);
            }
            if (mb_strtolower($params[1]) === 'ldap_default_cg' && ! empty($params[2])) {
                $contactGroupObj = new CentreonContactGroup($this->dependencyInjector);
                $params[2] = $contactGroupObj->getContactGroupID($params[2]);
            }
            $statement = $this->db->prepare(
                <<<'SQL'
                    DELETE FROM auth_ressource_info
                    WHERE ari_name = :name AND ar_id = :arId
                    SQL
            );
            $statement->bindValue(':name', $params[1], PDO::PARAM_STR);
            $statement->bindValue(':arId', $arId, PDO::PARAM_INT);
            $statement->execute();

            $statement = $this->db->prepare(
                <<<'SQL'
                    INSERT INTO auth_ressource_info (ari_value, ari_name, ar_id)
                    VALUES (:value, :name, :arId)
                    SQL
            );
            $statement->bindValue(':value', $params[2], PDO::PARAM_STR);
            $statement->bindValue(':name', $params[1], PDO::PARAM_STR);
            $statement->bindValue(':arId', $arId, PDO::PARAM_INT);
            $statement->execute();

        } else {
            throw new CentreonClapiException(self::UNKNOWNPARAMETER);
        }
    }

    /**
     * Set server param.
     *
     * @param null|mixed $parameters
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function setparamserver($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < 3) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        [$serverId, $key, $value] = $params;
        $key = mb_strtolower($key);
        if (! in_array($key, $this->serverParams, true)) {
            throw new CentreonClapiException(self::UNKNOWNPARAMETER);
        }
        $statement = $this->db->prepare(
            <<<SQL
                UPDATE auth_ressource_host
                SET {$key} = :value WHERE ldap_host_id = :id
                SQL
        );
        $statement->bindValue(':value', $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->bindValue(':id', $serverId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param mixed|null $filterName
     *
     * @return int|void
     * @throws Exception
     */
    public function export($filterName = null)
    {
        if (! $this->canBeExported($filterName)) {
            return 0;
        }

        $labelField = $this->object->getUniqueLabelField();
        $filters = [];
        if (! is_null($filterName)) {
            $filters[$labelField] = $filterName;
        }

        $configurationLdapObj = new Centreon_Object_Configuration_Ldap($this->dependencyInjector);
        $serverLdapObj = new Centreon_Object_Server_Ldap($this->dependencyInjector);
        $ldapList = $this->object->getList(
            '*',
            -1,
            0,
            $labelField,
            'ASC',
            $filters
        );

        foreach ($ldapList as $ldap) {
            echo $this->action . $this->delim . 'ADD' . $this->delim
                . $ldap['ar_name'] . $this->delim
                . $ldap['ar_description'] . $this->delim . "\n";

            echo $this->action . $this->delim . 'SETPARAM' . $this->delim
                . $ldap['ar_name'] . $this->delim
                . 'enable' . $this->delim
                . $ldap['ar_enable'] . $this->delim . "\n";

            $filters = ['`auth_ressource_id`' => $ldap['ar_id']];

            $ldapServerLabelField = $serverLdapObj->getUniqueLabelField();
            $ldapServerList = $serverLdapObj->getList(
                '*',
                -1,
                0,
                $ldapServerLabelField,
                'ASC',
                $filters
            );

            foreach ($ldapServerList as $server) {
                echo $this->action . $this->delim . 'ADDSERVER' . $this->delim
                    . $ldap['ar_name'] . $this->delim
                    . $server['host_address'] . $this->delim
                    . $server['host_port'] . $this->delim
                    . $server['use_ssl'] . $this->delim
                    . $server['use_tls'] . $this->delim . "\n";
            }

            $filters = ['`ar_id`' => $ldap['ar_id']];

            $ldapConfigurationLabelField = $configurationLdapObj->getUniqueLabelField();
            $ldapConfigurationList = $configurationLdapObj->getList(
                '*',
                -1,
                0,
                $ldapConfigurationLabelField,
                'ASC',
                $filters
            );

            foreach ($ldapConfigurationList as $configuration) {
                if ($configuration['ari_name'] !== 'ldap_dns_use_ssl'
                    && $configuration['ari_name'] !== 'ldap_dns_use_tls'
                ) {
                    if ($configuration['ari_name'] === 'ldap_contact_tmpl') {
                        $contactObj = new Centreon_Object_Contact($this->dependencyInjector);
                        $contactName = $contactObj->getParameters($configuration['ari_value'], 'contact_name');
                        $configuration['ari_value'] = $contactName['contact_name'];
                    }
                    if ($configuration['ari_name'] === 'ldap_default_cg') {
                        $contactGroupObj = new Centreon_Object_Contact_Group($this->dependencyInjector);
                        $contactGroupName = $contactGroupObj->getParameters($configuration['ari_value'], 'cg_name');
                        $configuration['ari_value'] = ! empty($contactGroupName['cg_name'])
                            ? $contactGroupName['cg_name']
                            : null;
                    }
                    echo $this->action . $this->delim . 'SETPARAM' . $this->delim
                        . $ldap['ar_name'] . $this->delim
                        . $configuration['ari_name'] . $this->delim
                        . $configuration['ari_value'] . $this->delim . "\n";
                }
            }
        }
    }

    /**
     * Checks if configuration name is unique.
     *
     * @param string $name
     * @param int $arId
     *
     * @return bool
     * @throws PDOException
     */
    protected function isUnique($name = '', $arId = 0)
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
                SELECT ar_name
                FROM auth_ressource
                WHERE ar_name = :name AND ar_id != :id
                SQL
        );
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':id', $arId, PDO::PARAM_INT);
        $stmt->execute();

        $res = $stmt->fetchAll();

        return ! (count($res));
    }
}
