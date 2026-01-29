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

namespace Centreon\Tests\Application\Validation;

use Centreon\Application\Validation\CentreonValidatorTranslator;
use Centreon\Test\Mock\CentreonUser;
use PHPUnit\Framework\TestCase;

/**
 * @group Centreon
 * @group DataRepresenter
 */
class CentreonValidatorTranslatorTest extends TestCase
{
    public function testTrans(): void
    {
        $user = new CentreonUser([
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
            'contact_theme' => 'light',
            'show_deprecated_pages' => false,
        ]);
        $translator = new CentreonValidatorTranslator($user);

        $this->assertEquals('test it', $translator->trans('test :it', [':it' => 'it']));
    }
}
