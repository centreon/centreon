<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 *
 */
namespace Centreon\Tests\Application\Validation;

use Centreon\Test\Mock\CentreonUser;
use PHPUnit\Framework\TestCase;
use Centreon\Application\Validation\CentreonValidatorTranslator;

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
