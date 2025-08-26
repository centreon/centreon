<?php
/**
 * Created by PhpStorm.
 * User: kduret
 * Date: 06/04/2017
 * Time: 10:17
 */

namespace Centreon\Test\Mock\DependencyInjector;


interface ServiceProviderInterface
{
    public function register(\Pimple\Container $container);

    public function terminate(\Pimple\Container $container);
}