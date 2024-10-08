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

namespace ConfigGenerateRemote\Relations;

use Exception;
use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;
use Pimple\Container;

/**
 * Class
 *
 * @class TrapsPreexec
 * @package ConfigGenerateRemote\Relations
 */
class TrapsPreexec extends AbstractObject
{
    /** @var int */
    private $useCache = 1;
    /** @var int */
    private $doneCache = 0;

    /** @var array */
    private $trapPreexecCache = [];

    /** @var string */
    protected $table = 'traps_preexec';
    /** @var string */
    protected $generateFilename = 'traps_preexec.infile';
    /** @var null */
    protected $stmtTrap = null;

    /** @var string[] */
    protected $attributesWrite = [
        'trap_id',
        'tpe_order',
        'tpe_string'
    ];

    /**
     * TrapsPreexec constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->buildCache();
    }

    /**
     * Build cache of trap preexec
     *
     * @return void
     */
    private function cacheTrapPreexec(): void
    {
        $stmt = $this->backendInstance->db->prepare(
            "SELECT *
            FROM traps_preexec"
        );

        $stmt->execute();
        $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($values as &$value) {
            if (!isset($this->trapPreexecCache[$value['trap_id']])) {
                $this->trapPreexecCache[$value['trap_id']] = [];
            }
            $this->trapPreexecCache[$value['trap_id']][] = &$value;
        }
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

        $this->cacheTrapPreexec();
        $this->doneCache = 1;
    }

    /**
     * Generate object
     *
     * @param int $trapId
     * @param array $trapPreexecCache
     *
     * @return void
     * @throws Exception
     */
    public function generateObject($trapId, $trapPreexecCache): void
    {
        foreach ($trapPreexecCache as $value) {
            $this->generateObjectInFile($value);
        }
    }

    /**
     * Get trap preexec from trap id
     *
     * @param int $trapId
     *
     * @return void
     * @throws Exception
     */
    public function getTrapPreexecByTrapId(int $trapId)
    {
        // Get from the cache
        if (isset($this->trapPreexecCache[$trapId])) {
            $this->generateObject($trapId, $this->trapPreexecCache[$trapId]);
            return $this->trapPreexecCache[$trapId];
        } elseif ($this->useCache == 1) {
            return null;
        }

        // We get unitary
        if (is_null($this->stmtTrap)) {
            $this->stmtTrap = $this->backendInstance->db->prepare(
                "SELECT *
                FROM traps_preexec
                WHERE trap_id = :trap_id"
            );
        }

        $this->stmtTrap->bindParam(':trap_id', $trapId, PDO::PARAM_INT);
        $this->stmtTrap->execute();
        $trapPreexecCache = [];
        foreach ($this->stmtTrap->fetchAll(PDO::FETCH_ASSOC) as &$value) {
            $trapPreexecCache[$value['traps_id']] = $value;
        }

        $this->generateObject($trapId, $trapPreexecCache[$trapId]);

        return $trapPreexecCache;
    }
}
