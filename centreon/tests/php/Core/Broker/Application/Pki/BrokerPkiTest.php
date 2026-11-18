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

namespace Tests\Core\Broker\Application\Pki;

use Core\Broker\Application\Pki\BrokerPki;
use Core\Broker\Application\Pki\X509;
use Core\Broker\Application\Repository\BrokerPkiRepositoryInterface;
use Core\Broker\Domain\Model\PkiCertificate;
use Security\Encryption;

/**
 * The table broker_pki in memory, with its conditional writes. $beforeWrite runs before each write, as another
 * replica writing first would.
 */
final class InMemoryBrokerPkiRepository implements BrokerPkiRepositoryInterface
{
    /** @var array<string, PkiCertificate> */
    public array $rows = [];

    /** @var (\Closure(InMemoryBrokerPkiRepository): void)|null */
    public ?\Closure $beforeWrite = null;

    public function find(string $subject): ?PkiCertificate
    {
        return $this->rows[$subject] ?? null;
    }

    public function add(PkiCertificate $certificate): bool
    {
        $this->interleave();
        if (isset($this->rows[$certificate->subject])) {
            return false;
        }
        $this->rows[$certificate->subject] = $certificate;

        return true;
    }

    public function replace(PkiCertificate $certificate, string $previousFingerprint): bool
    {
        $this->interleave();
        if (($this->rows[$certificate->subject] ?? null)?->fingerprint !== $previousFingerprint) {
            return false;
        }
        $this->rows[$certificate->subject] = $certificate;

        return true;
    }

    private function interleave(): void
    {
        $other = $this->beforeWrite;
        $this->beforeWrite = null;
        $other?->__invoke($this);
    }
}

function encryption(string $secret = 'a'): Encryption
{
    return (new Encryption())
        ->setFirstKey(base64_encode(str_repeat($secret, 32)))
        ->setSecondKey(base64_encode(str_repeat('s', 64)));
}

function extendedKeyUsage(string $certificate): string
{
    return (string) (openssl_x509_parse($certificate)['extensions']['extendedKeyUsage'] ?? '');
}

beforeEach(function (): void {
    $this->time = time();
    $this->repository = new InMemoryBrokerPkiRepository();
    $this->pki = new BrokerPki($this->repository, encryption(), new X509(), fn (): int => $this->time);
});

it('should create the certificate authority once, its key encrypted', function (): void {
    $authority = $this->pki->authority();

    $parsed = openssl_x509_parse($authority->certificate);
    expect($parsed['extensions']['basicConstraints'])->toContain('CA:TRUE')
        ->and($parsed['subject']['CN'])->toBe('Centreon broker CA')
        ->and($authority->encryptedKey)->not->toContain('PRIVATE KEY')
        ->and(encryption()->decrypt($authority->encryptedKey))->toContain('PRIVATE KEY')
        ->and($this->pki->authority())->toEqual($authority)
        ->and($this->repository->rows)->toHaveCount(1);
});

it('should keep the authority another replica stored first', function (): void {
    // Another replica, with the same database, creates its authority between our read and our write.
    $other = new BrokerPki($this->repository, encryption(), new X509(), fn (): int => $this->time);
    $this->repository->beforeWrite = function () use ($other): void {
        $other->authority();
    };

    $authority = $this->pki->authority();

    expect($authority)->toEqual($this->repository->rows[PkiCertificate::AUTHORITY])
        ->and($this->pki->authority())->toEqual($authority)
        ->and($this->pki->pollerCertificate(3, 'poller-3')->authority)->toBe($authority->certificate);
});

it('should issue a client certificate per poller, with its encrypted key', function (): void {
    $issued = $this->pki->pollerCertificate(3, 'poller-3');

    $parsed = openssl_x509_parse($issued->certificate);
    $key = $this->pki->privateKey($issued);
    expect($parsed['subject']['CN'])->toBe('poller-3')
        ->and(extendedKeyUsage($issued->certificate))->toBe('TLS Web Client Authentication')
        ->and($parsed['extensions']['basicConstraints'])->toContain('CA:FALSE')
        ->and($parsed['validTo_time_t'] - time())->toBeGreaterThan(364 * 86400)
        ->and((new X509())->isIssuedBy($issued->certificate, $issued->authority))->toBeTrue()
        ->and($issued->encryptedKey)->toStartWith('encrypt::')
        ->and($issued->encryptedKey)->not->toContain('PRIVATE KEY')
        ->and(openssl_x509_check_private_key($issued->certificate, $key))->toBeTrue()
        ->and(openssl_pkey_get_details(openssl_pkey_get_private($key))['ec']['curve_name'])->toBe('prime256v1')
        ->and($this->repository->rows[PkiCertificate::pollerSubject(3)]->encryptedKey)->not->toContain('PRIVATE');
});

it('should issue a server certificate for every name the pollers use', function (): void {
    $issued = $this->pki->centralCertificate(['broker', '10.0.0.5', '[::1]', 'broker', '']);

    $parsed = openssl_x509_parse($issued->certificate);
    expect(extendedKeyUsage($issued->certificate))->toBe('TLS Web Server Authentication')
        ->and($parsed['extensions']['subjectAltName'])
        ->toBe('IP Address:10.0.0.5, IP Address:0:0:0:0:0:0:0:1, DNS:broker');
});

it('should give the same certificate and key while they are valid', function (): void {
    $first = $this->pki->pollerCertificate(3, 'poller-3');
    $this->time += 300 * 86400;
    $central = $this->pki->centralCertificate(['broker', 'central']);

    expect($this->pki->pollerCertificate(3, 'poller-3'))->toEqual($first)
        // A name less is no reason to issue it again.
        ->and($this->pki->centralCertificate(['central']))->toEqual($central);
});

it('should issue again a certificate that expires within 30 days', function (): void {
    $first = $this->pki->pollerCertificate(3, 'poller-3');
    $notAfter = openssl_x509_parse($first->certificate)['validTo_time_t'];
    $this->time = $notAfter - 31 * 86400;
    expect($this->pki->pollerCertificate(3, 'poller-3'))->toEqual($first);

    $this->time = $notAfter - 29 * 86400;
    $renewed = $this->pki->pollerCertificate(3, 'poller-3');
    expect($renewed->certificate)->not->toBe($first->certificate);
    // Valid one year from now (OpenSSL's clock): not issued again.
    $this->time = time();
    expect($this->pki->pollerCertificate(3, 'poller-3'))->toEqual($renewed);
});

it('should issue again a certificate for another name or a new host', function (): void {
    $poller = $this->pki->pollerCertificate(3, 'poller-3');
    $central = $this->pki->centralCertificate(['broker']);

    expect($this->pki->pollerCertificate(3, 'renamed')->certificate)->not->toBe($poller->certificate)
        ->and($this->pki->centralCertificate(['broker', 'central.example.com'])->certificate)
        ->not->toBe($central->certificate);
});

it('should issue every certificate again when the authority is replaced', function (): void {
    $authority = $this->pki->authority();
    $poller = $this->pki->pollerCertificate(3, 'poller-3');

    $this->time = $authority->notAfter - 10 * 86400;
    $renewed = $this->pki->pollerCertificate(3, 'poller-3');

    expect($renewed->authority)->not->toBe($authority->certificate)
        ->and($renewed->certificate)->not->toBe($poller->certificate)
        ->and((new X509())->isIssuedBy($renewed->certificate, $renewed->authority))->toBeTrue();
});

it('should keep the certificate another replica renewed first', function (): void {
    $this->pki->pollerCertificate(3, 'poller-3');
    $other = new BrokerPki($this->repository, encryption(), new X509(), fn (): int => $this->time);
    $this->repository->beforeWrite = function () use ($other): void {
        $other->pollerCertificate(3, 'renamed');
    };

    $issued = $this->pki->pollerCertificate(3, 'renamed');

    expect($issued->certificate)->toBe($this->repository->rows[PkiCertificate::pollerSubject(3)]->certificate);
});

it('should refuse an authority whose key the engine-context key does not decrypt', function (): void {
    $this->pki->authority();
    $otherKey = new BrokerPki($this->repository, encryption('b'), new X509(), fn (): int => $this->time);

    expect(fn () => $otherKey->pollerCertificate(3, 'poller-3'))
        ->toThrow(\RuntimeException::class, 'cannot be decrypted with the engine-context key');
});

it('should refuse a name that is neither a host name nor an IP address', function (): void {
    expect(fn () => $this->pki->centralCertificate(['broker,DNS:evil.example.com']))
        ->toThrow(\InvalidArgumentException::class);
});
