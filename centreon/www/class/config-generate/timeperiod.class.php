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

/**
 * Class
 *
 * @class Timeperiod
 */
class Timeperiod extends AbstractObject
{
    /** @var null */
    private $timeperiods = null;

    /** @var string */
    protected $generate_filename = 'timeperiods.cfg';

    /** @var string */
    protected string $object_name = 'timeperiod';

    /** @var string */
    protected $attributes_select = '
        tp_id,
        tp_name as timeperiod_name,
        tp_alias as alias,
        tp_sunday as sunday,
        tp_monday as monday,
        tp_tuesday as tuesday,
        tp_wednesday as wednesday,
        tp_thursday as thursday,
        tp_friday as friday,
        tp_saturday as saturday
    ';

    /** @var string[] */
    protected $attributes_write = ['name', 'timeperiod_name', 'alias', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** @var string[] */
    protected $attributes_array = ['use', 'exclude'];

    /** @var string[] */
    protected $attributes_hash = ['exceptions'];

    /** @var null[] */
    protected $stmt_extend = ['include' => null, 'exclude' => null];

    /**
     * @throws PDOException
     * @return void
     */
    public function getTimeperiods(): void
    {
        $query = "SELECT {$this->attributes_select} FROM timeperiod";
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->execute();
        $this->timeperiods = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * @param $timeperiod_id
     *
     * @throws PDOException
     * @return int|void
     */
    protected function getTimeperiodExceptionFromId($timeperiod_id)
    {
        if (isset($this->timeperiods[$timeperiod_id]['exceptions'])) {
            return 1;
        }

        $query = 'SELECT days, timerange FROM timeperiod_exceptions WHERE timeperiod_id = :timeperiod_id';
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->bindParam(':timeperiod_id', $timeperiod_id, PDO::PARAM_INT);
        $stmt->execute();
        $exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->timeperiods[$timeperiod_id]['exceptions'] = [];
        foreach ($exceptions as $exception) {
            $this->timeperiods[$timeperiod_id]['exceptions'][$exception['days']] = $exception['timerange'];
        }
    }

    /**
     * @param $timeperiod_id
     * @param $db_label
     * @param $label
     *
     * @throws PDOException
     * @return void
     */
    protected function getTimeperiodExtendFromId($timeperiod_id, $db_label, $label)
    {
        if (! isset($this->timeperiods[$timeperiod_id][$label . '_cache'])) {
            if (is_null($this->stmt_extend[$db_label])) {
                $query = 'SELECT timeperiod_' . $db_label . '_id as period_id FROM timeperiod_' . $db_label
                    . '_relations WHERE timeperiod_id = :timeperiod_id';
                $this->stmt_extend[$db_label] = $this->backend_instance->db->prepare($query);
            }
            $this->stmt_extend[$db_label]->bindParam(':timeperiod_id', $timeperiod_id, PDO::PARAM_INT);
            $this->stmt_extend[$db_label]->execute();
            $this->timeperiods[$timeperiod_id][$label . '_cache']
                = $this->stmt_extend[$db_label]->fetchAll(PDO::FETCH_COLUMN);
        }

        $this->timeperiods[$timeperiod_id][$label] = [];
        foreach ($this->timeperiods[$timeperiod_id][$label . '_cache'] as $period_id) {
            $this->timeperiods[$timeperiod_id][$label][] = $this->generateFromTimeperiodId($period_id);
        }
    }

    /**
     * @param $timeperiod_id
     *
     * @throws PDOException
     * @return mixed|null
     */
    public function generateFromTimeperiodId($timeperiod_id)
    {
        if (is_null($timeperiod_id)) {
            return null;
        }
        if (is_null($this->timeperiods)) {
            $this->getTimeperiods();
        }

        if (! isset($this->timeperiods[$timeperiod_id])) {
            return null;
        }
        if ($this->checkGenerate($timeperiod_id)) {
            return $this->timeperiods[$timeperiod_id]['timeperiod_name'];
        }

        $this->timeperiods[$timeperiod_id]['name'] = $this->timeperiods[$timeperiod_id]['timeperiod_name'];
        $this->getTimeperiodExceptionFromId($timeperiod_id);
        $this->getTimeperiodExtendFromId($timeperiod_id, 'exclude', 'exclude');
        $this->getTimeperiodExtendFromId($timeperiod_id, 'include', 'use');

        $this->generateObjectInFile($this->timeperiods[$timeperiod_id], $timeperiod_id);

        return $this->timeperiods[$timeperiod_id]['timeperiod_name'];
    }
}
