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

declare(strict_types=1);

use Adaptation\Database\Connection\Model\ConnectionConfig;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // Parameters prefixed with "upgrade." are self-contained in this file.
    // The env vars (_CENTREON_VARLIB_, _CENTREON_PATH_, etc.) are written via putenv() in
    // centreon.config.php (itself generated from centreon.config.php.template by the RPM install).
    // They are resolved lazily at runtime — Symfony does not need them at container compile time.
    // TODO: once Delivery sets these env vars directly in the install scripts (outside of
    //       centreon.config.php), the putenv() calls in the template can be removed.
    $containerConfigurator->parameters()
        ->set('upgrade.lib_dir', '%env(_CENTREON_VARLIB_)%')
        // _CENTREON_PATH_ is set with a trailing slash (e.g. /usr/share/centreon/)
        ->set('upgrade.install_dir', '%env(_CENTREON_PATH_)%www/install')
        ->set('upgrade.engine_context_path', '/etc/centreon-engine/engine-context.json')
        ->set('upgrade.min_mariadb_version', '%env(_CENTREON_MARIA_DB_MIN_VERSION_)%')
        // TODO: replace with '%env(_CENTREON_MYSQL_MIN_VERSION_)%' once Delivery adds this env var.
        ->set('upgrade.min_mysql_version', '8.0')
        ->set('upgrade.modules_dir', '%env(_CENTREON_PATH_)%www/modules')
        ->set('upgrade.widgets_dir', '%env(_CENTREON_PATH_)%www/widgets')
        ->set('upgrade.centreon_path', '%env(_CENTREON_PATH_)%');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ConnectionConfig::class)
        ->args([
            '%env(hostCentreon)%',
            '%env(user)%',
            '%env(password)%',
            '%env(db)%',
            '%env(dbcstg)%',
        ]);

    $services->load('App\\Upgrade\\', __DIR__ . '/../../src/App/Upgrade');
};
