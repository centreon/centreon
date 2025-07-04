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
 * @class Centreon_Wizard
 */
class Centreon_Wizard
{
    /** @var string */
    private $_uuid = null;

    /** @var string */
    private $_name = null;

    /** @var array */
    private $_values = [];

    /** @var int */
    private $_lastUpdate = 0;

    /**
     * Centreon_Wizard constructor
     *
     * @param string $name The wizard name
     * @param string $uuid The wizard unique id
     */
    public function __construct($name, $uuid)
    {
        $this->_uuid = $uuid;
        $this->_name = $name;
        $this->_lastUpdate = time();
    }

    /**
     * Get values for a step
     *
     * @param int $step The step position
     *
     * @return array
     */
    public function getValues($step)
    {
        if (false === isset($this->_values[$step])) {
            return [];
        }

        return $this->_values[$step];
    }

    /**
     * Get a value
     *
     * @param int $step The step position
     * @param string $name The variable name
     * @param string $default The default value
     *
     * @return string
     */
    public function getValue($step, $name, $default = '')
    {
        if (false === isset($this->_values[$step]) || false === isset($this->_values[$step][$name])) {
            return $default;
        }

        return $this->_values[$step][$name];
    }

    /**
     * Add values for a step
     *
     * @param int $step The step position
     * @param array $post The post with values
     *
     * @return void
     */
    public function addValues($step, $post): void
    {
        // Reinit
        $this->_values[$step] = [];
        foreach ($post as $key => $value) {
            if (strncmp($key, 'step' . $step . '_', 6) === 0) {
                $this->_values[$step][str_replace('step' . $step . '_', '', $key)] = $value;
            }
        }
        $this->_lastUpdate = time();
    }

    /**
     * Test if the uuid of wizard
     *
     * @param string $uuid The unique id
     *
     * @return bool
     */
    public function testUuid($uuid)
    {
        return (bool) ($uuid == $this->_uuid);
    }

    /**
     * Magic method __sleep
     *
     * @return string[]
     */
    public function __sleep()
    {
        $this->_lastUpdate = time();

        return ['_uuid', '_lastUpdate', '_name', '_values'];
    }

    /**
     * Magic method __wakeup
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_lastUpdate = time();
    }
}
