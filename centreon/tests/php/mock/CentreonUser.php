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

/**
 * Mock class for resultset
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-license-manager
 * @subpackage test
 */
class CentreonUser extends \CentreonUser
{
    /** @var mixed|null */
    public $user_id;
    /** @var string|null */
    public $name;
    /** @var string|null */
    public $alias;
    /** @var string|null */
    public $email;
    /** @var mixed|null */
    public $lang;
    /** @var string */
    public $charset;
    /** @var mixed|null */
    public $passwd;
    /** @var mixed|null */
    public $token;
    /** @var mixed|null */
    public $admin;
    /** @var int */
    public $version;
    /** @var mixed|null */
    public $default_page;
    /** @var mixed|null */
    public $gmt;
    /** @var mixed|null */
    public $js_effects;
    /** @var null */
    public $is_admin;
    /** @var mixed|null */
    public $theme;

    /**
     * CentreonUser constructor
     *
     * @param $user
     */
    public function __construct($user)
    {
        $this->user_id = $user["contact_id"] ?? null;
        $this->name = isset($user["contact_name"]) ?
            html_entity_decode($user["contact_name"], ENT_QUOTES, "UTF-8") : null;
        $this->alias = isset($user["contact_alias"]) ?
            html_entity_decode($user["contact_alias"], ENT_QUOTES, "UTF-8") : null;
        $this->email = isset($user["contact_email"]) ?
            html_entity_decode($user["contact_email"], ENT_QUOTES, "UTF-8") : null;
        $this->lang = $user["contact_lang"] ?? null;
        $this->charset = "UTF-8";
        $this->passwd = $user["contact_passwd"] ?? null;
        $this->token = $user['contact_autologin_key'] ?? null;
        $this->admin = $user["contact_admin"] ?? null;
        $this->version = 3;
        $this->default_page = $user["default_page"] ?? null;
        $this->gmt = $user["contact_location"] ?? null;
        $this->js_effects = $user["contact_js_effects"] ?? null;
        $this->is_admin = null;
        $this->theme = $user['contact_theme'] ?? null;
    }
}
