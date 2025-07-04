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

set_include_path(implode(PATH_SEPARATOR, [realpath(__DIR__ . '/www/class'), realpath(__DIR__ . '/www/lib'), get_include_path()]));

// Centreon Autoload
spl_autoload_register(function ($sClass): void {
    $fileName = lcfirst($sClass);
    $fileNameType1 = __DIR__ . '/www/class/' . $fileName . '.class.php';
    $fileNameType2 = __DIR__ . '/www/class/' . $fileName . '.php';

    if (file_exists($fileNameType1)) {
        require_once $fileNameType1;
    } elseif (file_exists($fileNameType2)) {
        require_once $fileNameType2;
    }
});

function loadDependencyInjector()
{
    global $dependencyInjector;

    return $dependencyInjector;
}

// require composer file
$loader = require __DIR__ . '/vendor/autoload.php';

Doctrine\Common\Annotations\AnnotationRegistry::registerLoader([$loader, 'loadClass']);

// Retrieving Symfony environment variables for legacy code (APIv1,...)
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__ . '/.env');

require_once __DIR__ . '/container.php';
