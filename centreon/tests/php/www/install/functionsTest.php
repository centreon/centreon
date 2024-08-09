<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Www\Install\Steps;

$fileInstallFunctions = __DIR__ . '/../../../../www/install/functions.php';

// patch to fix an error when we run pest tests because install folder is deleted after installation in container
if (file_exists($fileInstallFunctions)) {

    require_once __DIR__ . '/../../../../www/install/functions.php';

    it('generates random password with lowercase, uppercase, number and special character', function () {
        $password = generatePassword();
        expect($password)->toHaveLength(12)
            ->and($password)->toMatch('/[0-9]+/')
            ->and($password)->toMatch('/[a-z]+/')
            ->and($password)->toMatch('/[A-Z]+/')
            ->and($password)->toMatch('/[@$!%*?&]+/');
    });

} else {
    it("tests of /www/install/functions.php skiped because install folder doesn't exist");
}
