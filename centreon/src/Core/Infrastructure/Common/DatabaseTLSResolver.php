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

namespace Core\Infrastructure\Common;

use Symfony\Component\Dotenv\Dotenv;

final readonly class DatabaseTLSResolver
{
    /**
     * @return array<int, mixed>
     */
    public static function getTLSOptions(): array
    {
        $options = [];

        (new Dotenv())->loadEnv(_CENTREON_PATH_ . '/.env');
        $verifyServerCert = $_ENV['DATABASE_VERIFY_SERVER_CERT'] ?? null;
        if ($verifyServerCert === null) {
            return $options;
        }
        $verifyServerCert = (bool) $verifyServerCert;
        $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verifyServerCert;

        if ($verifyServerCert === false) {
            return $options;
        }

        $envToPdoOption = [
            'DATABASE_CA_PATH'        => \PDO::MYSQL_ATTR_SSL_CA,
            'DATABASE_SSL_CERT_PATH' => \PDO::MYSQL_ATTR_SSL_CERT,
            'DATABASE_SSL_KEY_PATH'  => \PDO::MYSQL_ATTR_SSL_KEY,
        ];

        foreach ($envToPdoOption as $envKey => $pdoOption) {
            if (! empty($_ENV[$envKey])) {
                $options[$pdoOption] = $_ENV[$envKey];
            }
        }

        return $options;
    }
}
