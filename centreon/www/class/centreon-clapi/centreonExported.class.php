<?php
/**
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonClapi;

class CentreonExported
{
<<<<<<< HEAD
=======

>>>>>>> centreon/dev-21.10.x
    private $exported = array();
    private $ariane = array();
    private $filter = 0;
    private $filter_type = null;
    private $filter_ariane = null;

    /**
     * @var Singleton
     * @access private
     * @static
     */
<<<<<<< HEAD
    private static $instance = null;
=======
    private static $_instance = null;
>>>>>>> centreon/dev-21.10.x

    /**    *
     * @param void
     * @return void
     */
    private function __construct()
    {
    }

<<<<<<< HEAD
    public function arianePush($object, $id, $name)
=======
    public function ariane_push($object, $id, $name)
>>>>>>> centreon/dev-21.10.x
    {
        array_push($this->ariane, $object . ':' . $name . ':' . $id);
    }

<<<<<<< HEAD
    public function arianePop()
=======
    public function ariane_pop()
>>>>>>> centreon/dev-21.10.x
    {
        array_pop($this->ariane);
    }

<<<<<<< HEAD
    public function setFilter($value = 1)
=======
    public function set_filter($value = 1)
>>>>>>> centreon/dev-21.10.x
    {
        $this->filter = $value;
    }

<<<<<<< HEAD
    public function setOptions($options)
=======
    public function set_options($options)
>>>>>>> centreon/dev-21.10.x
    {
        if (isset($options['filter-type'])) {
            $this->filter_type = $options['filter-type'];
            if (!is_array($options['filter-type'])) {
                $this->filter_type = array($options['filter-type']);
            }
        }

        if (isset($options['filter-ariane'])) {
            $this->filter_ariane = $options['filter-ariane'];
            if (!is_array($options['filter-ariane'])) {
                $this->filter_ariane = array($options['filter-ariane']);
            }
        }
    }

<<<<<<< HEAD
    private function checkAriane($object, $id, $name)
=======
    private function check_ariane($object, $id, $name)
>>>>>>> centreon/dev-21.10.x
    {
        if (!is_null($this->filter_ariane)) {
            $ariane = join('#', $this->ariane);
            foreach ($this->filter_ariane as $filter) {
                if (preg_match('/' . $filter . '/', $ariane)) {
                    return 0;
                }
            }
            return 1;
        }

        return 0;
    }

<<<<<<< HEAD
    private function checkFilter($object, $id, $name)
=======
    private function check_filter($object, $id, $name)
>>>>>>> centreon/dev-21.10.x
    {
        if (!is_null($this->filter_type)) {
            foreach ($this->filter_type as $filter) {
                if (preg_match('/' . $filter . '/', $object)) {
                    return 0;
                }
            }
            return 1;
        }

        return 0;
    }

<<<<<<< HEAD
    public function isExported($object, $id, $name)
=======
    public function is_exported($object, $id, $name)
>>>>>>> centreon/dev-21.10.x
    {
        if ($this->filter == 0) {
            return 1;
        }

        if (isset($this->exported[$object][$id])) {
            return 1;
        }

        # check if there is some filters
<<<<<<< HEAD
        if ($this->checkFilter($object, $id, $name)) {
            return 1;
        }
        if ($this->checkAriane($object, $id, $name)) {
=======
        if ($this->check_filter($object, $id, $name)) {
            return 1;
        }
        if ($this->check_ariane($object, $id, $name)) {
>>>>>>> centreon/dev-21.10.x
            return 1;
        }

        if (!isset($this->exported[$object]) || !is_array($this->exported[$object])) {
            $this->exported[$object] = array();
        }
        $this->exported[$object][$id] = 1;
        return 0;
    }

    /**
     *
     * @param void
     * @return CentreonExported
     */
    public static function getInstance()
    {
<<<<<<< HEAD
        if (is_null(self::$instance)) {
            self::$instance = new CentreonExported();
        }

        return self::$instance;
=======
        if (is_null(self::$_instance)) {
            self::$_instance = new CentreonExported();
        }

        return self::$_instance;
>>>>>>> centreon/dev-21.10.x
    }
}
