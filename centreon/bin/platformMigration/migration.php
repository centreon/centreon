<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace CloudMigration;

use Symfony\Component\Console\Application;

include __DIR__ .'/../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    if (! str_starts_with($class, __NAMESPACE__)) {

        return;
    }

    $file = __DIR__ . '/command/' . mb_substr($class, mb_strlen(__NAMESPACE__) + 1) . '.php';

    if (is_file($file)) {
        include $file;

        return;
    }

    $file = __DIR__ . '/common/' . mb_substr($class, mb_strlen(__NAMESPACE__) + 1) . '.php';

    if (is_file($file)) {
        include $file;
    }
});

$app = new Application('PlatformMigration', '24.04.0');

$app->add(new MigrationCommand());
$app->add(new MigrationList());
$app->add(new MigrationAll());

$app->setDefaultCommand(MigrationList::getDefaultName() ?? '');
$app->run();