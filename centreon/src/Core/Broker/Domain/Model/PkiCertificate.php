<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Broker\Domain\Model;

/**
 * A certificate of the managed PKI of the BBDO connections, as stored: the certificate in clear, its private key
 * encrypted with the engine-context key (Security\Encryption, the format of the "encrypt::" values).
 */
final readonly class PkiCertificate
{
    /** Subject of the certificate authority. */
    public const AUTHORITY = 'ca';
    /** Subject of the certificate of the central broker inputs. */
    public const CENTRAL = 'central';

    /**
     * @param string $subject what the certificate is for: AUTHORITY, CENTRAL or pollerSubject()
     * @param string $certificate PEM
     * @param string $encryptedKey PEM private key encrypted with the engine-context key, without "encrypt::"
     * @param string $fingerprint SHA-256 of the certificate (DER), hexadecimal
     * @param int $notAfter end of validity, Unix time
     */
    public function __construct(
        public string $subject,
        public string $certificate,
        public string $encryptedKey,
        public string $fingerprint,
        public int $notAfter,
    ) {
    }

    public static function pollerSubject(int $pollerId): string
    {
        return "poller:{$pollerId}";
    }
}
