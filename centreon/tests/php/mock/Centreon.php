<?php
/**
 * Copyright 2016 Centreon
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
 */
namespace Centreon\Test\Mock;

use Centreon\Test\Mock\CentreonUser;

/**
 * Mock class for resultset
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-license-manager
 * @subpackage test
 */
class Centreon extends \Centreon
{
    /** @var \Centreon\Test\Mock\CentreonUser */
    public $user;

    /**
     * Centreon constructor
     *
     * @param $userInfos
     */
    public function __construct($userInfos = null)
    {
        $userInfos = [
            'contact_id' => '1',
            'contact_name' => 'John Doe',
            'contact_alias' => 'johny',
            'contact_email' => 'john.doe@mail.loc',
            'contact_lang' => 'en',
            'contact_passwd' => '123',
            'contact_autologin_key' => '123',
            'contact_admin' => '1',
            'default_page' => '',
            'contact_location' => '0',
            'contact_js_effects' => '0',
            'contact_theme' => 'light'
        ];

        $this->user = new CentreonUser($userInfos);
    }

    /**
     * @return void
     */
    public function generateSession() : void {
        $_SESSION['centreon'] = $this;
    }
}
