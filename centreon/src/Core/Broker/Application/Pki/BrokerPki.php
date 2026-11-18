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

namespace Core\Broker\Application\Pki;

use Core\Broker\Application\Repository\BrokerPkiRepositoryInterface;
use Core\Broker\Domain\Model\PkiCertificate;
use Security\Interfaces\EncryptionInterface;

/**
 * The PKI of the BBDO connections from the pollers to the central broker (feature flag broker_managed_pki): one
 * certificate authority, created on first need, a client certificate per poller and a server certificate for the
 * central broker inputs. Everything is in the database, the private keys encrypted with the engine-context key, so
 * that every web replica issues from the same authority without a shared volume.
 *
 * A certificate is issued again only when it is missing, not issued by the current authority, expiring within
 * RENEW_BEFORE, made for another name, or its key cannot be decrypted: an unchanged configuration exports the same
 * certificates, and so the same files and documents.
 */
final class BrokerPki
{
    public const FEATURE_FLAG = 'broker_managed_pki';
    public const AUTHORITY_DAYS = 3650;
    public const CERTIFICATE_DAYS = 365;
    public const RENEW_BEFORE = 30 * 86400;
    private const AUTHORITY_NAME = 'Centreon broker CA';
    private const CENTRAL_NAME = 'centreon-central-broker';

    /** @var \Closure(): int */
    private readonly \Closure $now;

    /**
     * @param EncryptionInterface $encryption with the engine-context keys
     * @param (\Closure(): int)|null $now Unix time
     */
    public function __construct(
        private readonly BrokerPkiRepositoryInterface $repository,
        private readonly EncryptionInterface $encryption,
        private readonly X509 $x509 = new X509(),
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    /**
     * The certificate authority, created when there is none. Two replicas creating it at once keep the first one
     * stored. Replaced when it expires within RENEW_BEFORE: every certificate is then issued again.
     *
     * @throws \Throwable
     */
    public function authority(): PkiCertificate
    {
        $current = $this->repository->find(PkiCertificate::AUTHORITY);
        if ($current !== null && $current->notAfter - ($this->now)() > self::RENEW_BEFORE) {
            if ($this->decryptKey($current) === null) {
                throw new \RuntimeException(
                    'The key of the broker certificate authority cannot be decrypted with the engine-context key'
                );
            }

            return $current;
        }
        [$certificate, $key] = $this->x509->newAuthority(self::AUTHORITY_NAME, self::AUTHORITY_DAYS);
        $created = $this->stored(PkiCertificate::AUTHORITY, $certificate, $key);
        $written = $current === null
            ? $this->repository->add($created)
            : $this->repository->replace($created, $current->fingerprint);

        return $written ? $created : ($this->repository->find(PkiCertificate::AUTHORITY)
            ?? throw new \RuntimeException('The broker certificate authority disappeared'));
    }

    /**
     * Client certificate of a poller (common name: the poller name), for its connections to the central broker.
     *
     * @throws \Throwable
     */
    public function pollerCertificate(int $pollerId, string $pollerName): IssuedCertificate
    {
        return $this->leaf(PkiCertificate::pollerSubject($pollerId), $pollerName, [], X509::CLIENT);
    }

    /**
     * Server certificate of the central broker inputs, valid for every name the pollers reach it by.
     *
     * @param list<string> $names host names and IP addresses of the outputs of the pollers
     *
     * @throws \Throwable
     */
    public function centralCertificate(array $names): IssuedCertificate
    {
        $names = array_values(array_unique(array_map(
            static fn (string $name): string => strtolower(trim($name, '[] ')),
            array_filter($names, static fn (string $name): bool => trim($name, '[] ') !== '')
        )));
        sort($names);

        return $this->leaf(PkiCertificate::CENTRAL, self::CENTRAL_NAME, $names ?: ['localhost'], X509::SERVER);
    }

    /**
     * The private key of a certificate, decrypted, for the files of the C++ Broker.
     *
     * @throws \RuntimeException
     */
    public function privateKey(IssuedCertificate $issued): string
    {
        $key = $this->encryption->decrypt(substr($issued->encryptedKey, strlen('encrypt::')));
        if ($key === null) {
            throw new \RuntimeException('A broker private key cannot be decrypted with the engine-context key');
        }

        return $key;
    }

    /**
     * @param list<string> $names
     * @param X509::SERVER|X509::CLIENT $usage
     *
     * @throws \Throwable
     */
    private function leaf(string $subject, string $commonName, array $names, string $usage): IssuedCertificate
    {
        $authority = $this->authority();
        $current = $this->repository->find($subject);
        if ($current !== null && ! $this->needsIssue($current, $authority, $commonName, $names)) {
            return $this->issued($authority, $current);
        }
        $authorityKey = $this->decryptKey($authority)
            ?? throw new \RuntimeException('The key of the broker certificate authority cannot be decrypted');
        [$certificate, $key] = $this->x509->issue(
            $authority->certificate,
            $authorityKey,
            $commonName,
            $names,
            $usage,
            self::CERTIFICATE_DAYS
        );
        $created = $this->stored($subject, $certificate, $key);
        $written = $current === null
            ? $this->repository->add($created)
            : $this->repository->replace($created, $current->fingerprint);
        if ($written) {
            return $this->issued($authority, $created);
        }

        // Another replica issued it meanwhile: use that one.
        $stored = $this->repository->find($subject)
            ?? throw new \RuntimeException("The broker certificate of {$subject} disappeared");

        return $this->issued($authority, $stored);
    }

    /**
     * @param list<string> $names
     */
    private function needsIssue(
        PkiCertificate $current,
        PkiCertificate $authority,
        string $commonName,
        array $names,
    ): bool {
        if ($current->notAfter - ($this->now)() <= self::RENEW_BEFORE) {
            return true;
        }
        if (! $this->x509->isIssuedBy($current->certificate, $authority->certificate)) {
            return true;
        }
        $described = $this->x509->describe($current->certificate);
        if ($described['commonName'] !== $commonName || array_diff($names, $described['names']) !== []) {
            return true;
        }

        return $this->decryptKey($current) === null;
    }

    private function issued(PkiCertificate $authority, PkiCertificate $certificate): IssuedCertificate
    {
        return new IssuedCertificate(
            $authority->certificate,
            $certificate->certificate,
            'encrypt::' . $certificate->encryptedKey
        );
    }

    /**
     * @throws \RuntimeException
     */
    private function stored(string $subject, string $certificate, string $key): PkiCertificate
    {
        $described = $this->x509->describe($certificate);

        return new PkiCertificate(
            $subject,
            $certificate,
            $this->encryption->crypt($key),
            $described['fingerprint'],
            $described['notAfter']
        );
    }

    private function decryptKey(PkiCertificate $certificate): ?string
    {
        try {
            return $this->encryption->decrypt($certificate->encryptedKey);
        } catch (\Throwable) {
            return null;
        }
    }
}
