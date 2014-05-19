<?php
/*
 * Copyright 2005-2014 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS 
 * permission to link this program with independent modules to produce an executable, 
 * regardless of the license terms of these independent modules, and to copy and 
 * distribute the resulting executable under terms of MERETHIS choice, provided that 
 * MERETHIS also meet, for each linked independent module, the terms  and conditions 
 * of the license of that module. An independent module is a module which is not 
 * derived from this program. If you modify this program, you may extend this 
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 * 
 * For more information : contact@centreon.com
 * 
 */

namespace Centreon\Internal\Install;

class Upgrade
{
    private static $coreModules = array(
        'centreon-home',
        'centreon-security',
        'centreon-configuration',
        'centreon-realtime',
        'centreon-customview',
        'centreon-bam',
    );
    
    public static function doUpgrade($origin = '3.0.0')
    {
        if (version_compare($origin, '3.0.0','<')) {
            self::upgradeFrom2X();
        } else {
            self::upgradeFrom3X();
        }
    }
    
    public static function checkForUpdate()
    {
        
    }
    
    private static function upgradeFrom2X()
    {
        \Centreon\Internal\Db\Installer::updateDb('migrate');
        
        $di = \Centreon\Internal\Di::getDefault();
        $config = $di->get('config');
        $centreonPath = rtrim($config->get('global', 'centreon_path'), '/');
        
        self::setUpFormValidators();
        
        foreach (self::$coreModules as $coreModule) {
            $commonName = str_replace(' ', '', ucwords(str_replace('-', ' ', $coreModule)));

            $moduleDirectory = $centreonPath
                . '/modules/'
                . $commonName
                . 'Module/';

            if (!file_exists(realpath($moduleDirectory . 'install/config.json'))) {
                throw new \Exception("The module $commonName is not valid because of a missing configuration file");
            }
            $moduleInfo = json_decode(file_get_contents($moduleDirectory . 'install/config.json'), true);
            // Launched Install
            $classCall = '\\'.$commonName.'\\Install\\Installer';
            $moduleInstaller = new $classCall($moduleDirectory, $moduleInfo);

            // Check if all dependencies are satisfied
            try {
                $moduleInstaller->install();
            } catch (\Exception $e) {
                $moduleInstaller->remove();
                echo '<pre>';
                echo $e->getMessage();
                var_dump(debug_backtrace());
                echo '</pre>';
            }
        }
    }
    
    private static function upgradeFrom3X()
    {
        
    }
    
    private static function setUpFormValidators()
    {
        $validators = array(
            "INSERT INTO form_validator(name, action) VALUES ('email', '/validator/email')",
            "INSERT INTO form_validator(name, action) VALUES ('resolveDns', '/validator/resolvedns')",
            "INSERT INTO form_validator(name, action) VALUES ('ipAddress', '/validator/ipaddress')",
            "INSERT INTO form_validator(name, action) VALUES ('unique', '/validator/unique')",
            "INSERT INTO form_validator(name, action) VALUES ('forbiddenChar', '/validator/forbiddenchar')",
            "INSERT INTO form_validator(name, action) VALUES ('circularDependency', '/validator/circular')"
        );
        
        $db = \Centreon\Internal\Di::getDefault()->get('db_centreon');
        
        foreach ($validators as $validator) {
            $db->exec($validator);
        }
    }
}