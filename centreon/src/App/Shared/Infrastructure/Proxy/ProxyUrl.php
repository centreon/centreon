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

namespace App\Shared\Infrastructure\Proxy;

final readonly class ProxyUrl
{
    /**
     * Build a proxy URL (protocol://[user:password@]host[:port]) from the
     * proxy entries of the legacy `options` table, as configured under
     * Administration > Parameters > Centreon UI.
     *
     * @param array<string, string|null> $options
     */
    public static function fromOptions(array $options): ?string
    {
        $url = trim((string) ($options['proxy_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        $protocol = 'http';
        if (preg_match('~^(https?)://(.+)$~', $url, $matches) === 1) {
            $protocol = $matches[1];
            $url = $matches[2];
        }

        $credentials = '';
        $user = trim((string) ($options['proxy_user'] ?? ''));
        $password = (string) ($options['proxy_password'] ?? '');
        // vault-stored proxy passwords can not be resolved in this container
        // yet: use the proxy without authentication rather than sending the
        // vault path as a password
        if ($user !== '' && ! str_starts_with($password, 'secret::')) {
            $credentials = rawurlencode($user);
            if ($password !== '') {
                $credentials .= ':' . rawurlencode($password);
            }
            $credentials .= '@';
        }

        $port = trim((string) ($options['proxy_port'] ?? ''));
        $portSuffix = $port !== '' ? ':' . (int) $port : '';

        return "{$protocol}://{$credentials}{$url}{$portSuffix}";
    }
}
