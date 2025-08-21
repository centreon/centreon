<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Centreon\Test\Mock\Object;


class BaseObject
{
    protected $incrementalId = 1;
    private static $countFunction = array();
    
    public function __construct($fakeDb)
    {
    }

    protected function realResult($expectedResult)
    {
        global $customResult;

        $stacktrace = debug_backtrace();
        $calledMethod = $stacktrace[2]['function'];

        if (isset(self::$countFunction[$calledMethod])) {
            self::$countFunction[$calledMethod]++;
        } else {
            self::$countFunction[$calledMethod] = 0;
        }

        $result = $expectedResult;

        if (array_key_exists($calledMethod, $customResult)) {

            if (!is_array($customResult[$calledMethod])) {

                $result = $customResult[$calledMethod];

            } else if (isset($customResult[$calledMethod]['at']) &&
                array_key_exists(self::$countFunction[$calledMethod], $customResult[$calledMethod]['at'])) {

                $result = $customResult[$calledMethod]['at'][self::$countFunction[$calledMethod]];

            } else if (array_key_exists('any', $customResult[$calledMethod])) {

                $result = $customResult[$calledMethod]['any'];

            }
        }

        return $result;
    }
    
    protected function getIncrementedId()
    {
        return $this->realResult($this->incrementalId++);
    }
}
