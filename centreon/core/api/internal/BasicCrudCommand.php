<?php

/*
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
namespace Centreon\Api\Internal;

use Centreon\Api\Internal\BasicCrud;

/**
 * Description of BasicCrudCommand
 *
 * @author lionel
 */
class BasicCrudCommand extends BasicCrud
{
    /**
     * 
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 
     * @param type $fields
     * @param type $count
     * @param type $offset
     */
    public function listAction($fields = null, $count = -1, $offset = 0)
    {
        $objectList = parent::listAction($fields, $count, $offset);
        
        // Displaying
        if (count($objectList) > 0) {
            $selectedFields = array_keys($objectList[0]);
            $result = implode(';', $selectedFields) . "\n";
            foreach ($objectList as $object) {
                $result .= implode(';', $object) . "\n";
            }
        } else {
            $result = _("No result found");
        }
        
        echo $result;
    }
    
    /**
     * 
     * @param type $object
     * @param type $fields
     * @param type $linkedObject
     */
    public function showAction($object, $fields = null, $linkedObject = '')
    {
        $myObject = parent::showAction($object, $fields, $linkedObject);
        
        $result = '';
        foreach ($myObject as $key => $value) {
            $result .= $key . ': ' . $value . "\n";
        }
        
        echo $result;
    }
    
    /**
     * Action for add
     * 
     * @param string $params
     */
    public function createAction($params)
    {
        parent::createAction($params);
    }
    
    /**
     * Action for update
     * 
     * @param string $object
     * @param string $params
     */
    public function updateAction($object, $params)
    {
        parent::updateAction($object, $params);
    }

    /**
     * Action for delete
     * 
     * @param type $object
     */
    public function deleteAction($object)
    {
        parent::deleteAction($object);
    }
    
    /**
     * 
     */
    public function duplicateAction()
    {
        parent::duplicateAction();
    }
    
    /**
     * 
     * @cmdObject string $object-name the name of the object
     */
    public function getSlugAction($object){
        
        $repository = $this->repository;
        $slug = $repository::getSlugByUniqueField($object);
        \Centreon\Internal\Utils\CommandLine\InputOutput::display($slug, true, 'green');
        
    }
    
    
    
    private function echoTable($inputTable,$tableInfos){
        $cnt = 0;
        foreach($tableInfos as $infos){
            echo '-+-';
            for($i = 0; $i < $infos['size']; $i++){
                echo '-';
            }
            $cnt = $cnt + 1;    
        }
        echo '-+';
        echo "\n";
        $cnt = 0;
        foreach($tableInfos as $infos){
            echo ' | ';
            echo $infos['header'];
            for($i = (strlen($infos['header']) + 1); $i <= $infos['size']; $i++){
                echo ' ';
            }
            $cnt = $cnt + 1;    
        }
        echo ' |';
        echo "\n";
        foreach($inputTable as $inputLines){
            $cnt = 0;
            foreach($tableInfos as $infos){
                echo '-+-';
                for($i = 0; $i < $infos['size']; $i++){
                    echo '-';
                }
                $cnt = $cnt + 1;    
            }
            echo '-+';
            echo "\n";
            $cnt = 0;
            foreach($inputLines as $inputField){
                echo ' | ';
                echo  $inputField;
                for($i = (strlen($inputField) + 1); $i <= $tableInfos[$cnt]['size']; $i++){
                    echo ' ';
                }
                $cnt = $cnt + 1;
            }
            echo ' |';
            echo "\n";
        }
        $cnt = 0;
        foreach($tableInfos as $infos){
            echo '-+-';
            for($i = 0; $i < $infos['size']; $i++){
                echo '-';
            }
            $cnt = $cnt + 1;    
        }
        echo '-+';
        echo "\n";
        
        
    }
    
    
    /**
     * Display result of a command as table in command line
     * 
     * @param array $headers
     * @param array $inputTable
     */
    public function tableDisplay($headers, $inputTable){

        $cnt = 0;
        foreach($headers as $header){
            $tableInfos[$cnt]['size'] = strlen($header);
            $tableInfos[$cnt]['header'] = $header;
            $cnt = $cnt + 1;
        }
        
        foreach($inputTable as $inputLines){
            $cnt = 0;
            foreach($inputLines as $keys=>$inputField){
                if(array_key_exists($keys,$headers)){
                    $lenght = strlen($inputField);
                    if($tableInfos[$cnt]['size'] < $lenght){
                        $tableInfos[$cnt]['size'] = $lenght;
                    }
                    $cnt = $cnt + 1;    
                }
            }
        }

        $this->echoTable($inputTable,$tableInfos);
        
    }

    
}
