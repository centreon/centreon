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

use Core\Common\Application\UseCase\VaultTrait;
use Pimple\Container;

/**
 * Class
 *
 * @class Resource
 */
class Resource extends AbstractObject
{
    use VaultTrait;

    /** @var null */
    private $connectors = null;
    /** @var string */
    protected $generate_filename = 'resource.cfg';
    /** @var string */
    protected string $object_name;
    /** @var null|\PDOStatement */
    protected $stmt = null;
    /** @var string[] */
    protected $attributes_hash = ['resources'];

    /**
     * Macro constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);

        if (! $this->isVaultEnabled) {
            $this->getVaultConfigurationStatus();
        }
    }

    /**
     * @param $poller_id
     *
     * @return int|void
     * @throws PDOException
     */
    public function generateFromPollerId($pollerId)
    {
        if (is_null($pollerId)) {
            return 0;
        }

        if (is_null($this->stmt)) {
            $this->stmt = $this->backend_instance->db->prepare(<<<SQL
                SELECT resource_name, resource_line, is_password, ns.is_encryption_ready
                FROM cfg_resource_instance_relations, cfg_resource
                INNER JOIN nagios_server ns
                    ON ns.id = instance_id
                WHERE instance_id = :poller_id
                    AND cfg_resource_instance_relations.resource_id = cfg_resource.resource_id
                    AND cfg_resource.resource_activate = '1';
                SQL
            );
        }
        $this->stmt->bindParam(':poller_id', $pollerId, PDO::PARAM_INT);
        $this->stmt->execute();

        $object = ['resources' => []];
        $vaultPaths = [];
        $isPassword = [];

        $results = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $value) {
            if ((bool) $value['is_password'] === true) {
                $isPassword[$value['resource_name']] = true;
            }
            $object['resources'][$value['resource_name']] = $value['resource_line'];
            if ($this->isAVaultPath($value['resource_line'])) {
                $vaultPaths[] = $value['resource_line'];
            }
        }
        if ($this->isVaultEnabled && $this->readVaultRepository !== null) {
            $vaultData = $this->readVaultRepository->findFromPaths($vaultPaths);
            foreach ($vaultData as $vaultValues) {
                foreach ($vaultValues as $vaultKey => $vaultValue) {
                    if (array_key_exists($vaultKey, $object['resources']) || array_key_exists('$' . $vaultKey . '$', $object['resources'])) {
                        $object['resources'][$vaultKey] = $vaultValue;
                    }
                }
            }
        }
        $statement = $this->backend_instance->db->prepare(<<<SQL
            SELECT is_encryption_ready FROM nagios_server WHERE nagios_server.id = :pollerId
            SQL
        );
        $statement->bindValue(':pollerId', $pollerId, \PDO::PARAM_INT);
        $statement->execute();

        $shouldBeEncrypted = (bool) $statement->fetchColumn();
        foreach ($object['resources'] as $macroKey => &$macroValue) {
            if (isset($isPassword[$macroKey])) {
                $macroValue =  $shouldBeEncrypted
                ? 'encrypt::' . $this->engineContextEncryption->crypt($macroValue)
                : 'raw::' . $this->engineContextEncryption->crypt($macroValue);
            }
        }

        $this->generateFile($object);
    }
}
