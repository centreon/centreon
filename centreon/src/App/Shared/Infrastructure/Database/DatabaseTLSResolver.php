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

namespace App\Shared\Infrastructure\Database;

final class DatabaseTLSResolver
{
    /** @var array<int, mixed>|null */
    private static ?array $cachedOptions = null;

    /**
     * @return array<int, mixed>
     */
    public static function getTLSOptions(): array
    {
        if (self::$cachedOptions !== null) {
            return self::$cachedOptions;
        }

        $options = [];

        $sslEnabled = $_ENV['DATABASE_SSL_ENABLED'] ?? null;
        if ($sslEnabled === null || ! (bool) $sslEnabled) {
            // SSL not enabled, return empty options (no SSL)
            return $options;
        }

        // SSL is enabled, configure options
        $verifyServerCert = (bool) ($_ENV['DATABASE_VERIFY_SERVER_CERT'] ?? false);
        $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verifyServerCert;

        if ($verifyServerCert === false) {
            // Force SSL connection without certificate verification
            // This is required when MySQL has --require_secure_transport=ON
            // and using self-signed certificates
            $options[\PDO::MYSQL_ATTR_SSL_CA] = '';

            return $options;
        }

        // SSL with certificate verification enabled
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

        self::$cachedOptions = $options;

        return self::$cachedOptions;
    }
}
