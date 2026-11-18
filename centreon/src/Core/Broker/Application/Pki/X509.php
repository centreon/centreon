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

/**
 * X.509 with ext-openssl: EC P-256 keys, SHA-256 signatures. Every call gets its own OpenSSL configuration (a
 * temporary file): extensions are only set through one, and a static PHP build may have no default file. No method
 * puts a private key in an error message.
 */
final class X509
{
    public const SERVER = 'serverAuth';
    public const CLIENT = 'clientAuth';
    private const CURVE = 'prime256v1';
    private const DIGEST = 'sha256';

    /**
     * A self-signed certificate authority.
     *
     * @throws \RuntimeException
     *
     * @return array{string, string} certificate and private key, PEM
     */
    public function newAuthority(string $commonName, int $days): array
    {
        return $this->withConfiguration(
            <<<'CNF'
                [ca_ext]
                basicConstraints = critical,CA:TRUE
                keyUsage = critical,keyCertSign,cRLSign
                subjectKeyIdentifier = hash
                CNF,
            function (string $configuration) use ($commonName, $days): array {
                $key = $this->newKey($configuration);
                $certificate = $this->sign($commonName, $key, null, $key, $days, $configuration, 'ca_ext');

                return [$certificate, $this->exportKey($key, $configuration)];
            }
        );
    }

    /**
     * A certificate issued by the authority, for a TLS server or client.
     *
     * @param list<string> $names DNS names and IP addresses of the subjectAltName
     * @param self::SERVER|self::CLIENT $usage extended key usage
     *
     * @throws \InvalidArgumentException when a name is neither a host name nor an IP address
     * @throws \RuntimeException
     *
     * @return array{string, string} certificate and private key, PEM
     */
    public function issue(
        string $authorityCertificate,
        string $authorityKey,
        string $commonName,
        array $names,
        string $usage,
        int $days,
    ): array {
        $alternativeNames = array_map(self::alternativeName(...), $names);
        $extensions = "[leaf_ext]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature\n"
            . "extendedKeyUsage = {$usage}\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid\n";
        if ($alternativeNames !== []) {
            $extensions .= 'subjectAltName = ' . implode(',', $alternativeNames) . "\n";
        }

        return $this->withConfiguration(
            $extensions,
            function (string $configuration) use ($authorityCertificate, $authorityKey, $commonName, $days): array {
                $issuerKey = openssl_pkey_get_private($authorityKey);
                if ($issuerKey === false) {
                    throw new \RuntimeException('The key of the certificate authority is not a private key');
                }
                $key = $this->newKey($configuration);
                $certificate = $this->sign(
                    $commonName,
                    $key,
                    $authorityCertificate,
                    $issuerKey,
                    $days,
                    $configuration,
                    'leaf_ext'
                );

                return [$certificate, $this->exportKey($key, $configuration)];
            }
        );
    }

    /**
     * @throws \RuntimeException
     *
     * @return array{commonName: string, names: list<string>, notAfter: int, fingerprint: string}
     */
    public function describe(string $certificate): array
    {
        $parsed = openssl_x509_parse($certificate);
        $fingerprint = openssl_x509_fingerprint($certificate, self::DIGEST);
        if ($parsed === false || $fingerprint === false) {
            throw new \RuntimeException('Not a PEM certificate');
        }
        $names = [];
        foreach (explode(',', (string) ($parsed['extensions']['subjectAltName'] ?? '')) as $name) {
            $name = trim($name);
            foreach (['DNS:', 'IP Address:'] as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $names[] = strtolower(substr($name, strlen($prefix)));
                }
            }
        }

        return [
            'commonName' => (string) ($parsed['subject']['CN'] ?? ''),
            'names' => $names,
            'notAfter' => (int) $parsed['validTo_time_t'],
            'fingerprint' => $fingerprint,
        ];
    }

    public function isIssuedBy(string $certificate, string $authorityCertificate): bool
    {
        $authorityKey = openssl_pkey_get_public($authorityCertificate);

        return $authorityKey !== false && openssl_x509_verify($certificate, $authorityKey) === 1;
    }

    /**
     * subjectAltName entry of a host name or an IP address; anything else could inject OpenSSL configuration.
     *
     * @throws \InvalidArgumentException
     */
    private static function alternativeName(string $name): string
    {
        $address = trim($name, '[]');
        if (filter_var($address, FILTER_VALIDATE_IP) !== false) {
            return "IP:{$address}";
        }
        if (preg_match('/^(?=.{1,253}$)[A-Za-z0-9_]([A-Za-z0-9_-]*[A-Za-z0-9_])?(\.[A-Za-z0-9_]([A-Za-z0-9_-]*[A-Za-z0-9_])?)*$/', $name) === 1) {
            return 'DNS:' . strtolower($name);
        }

        throw new \InvalidArgumentException(sprintf('"%s" is neither a host name nor an IP address', $name));
    }

    /**
     * @template T
     *
     * @param \Closure(string): T $action called with the path of the configuration
     *
     * @throws \RuntimeException
     *
     * @return T
     */
    private function withConfiguration(string $extensions, \Closure $action): mixed
    {
        $path = tempnam(sys_get_temp_dir(), 'centreon-pki-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create the OpenSSL configuration file');
        }
        try {
            $content = "[req]\ndistinguished_name = req_dn\nprompt = no\n[req_dn]\nCN = unused\n" . $extensions . "\n";
            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException('Cannot write the OpenSSL configuration file');
            }

            return $action($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function newKey(string $configuration): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new([
            'config' => $configuration,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => self::CURVE,
        ]);
        if ($key === false) {
            throw new \RuntimeException('Cannot create a key: ' . self::errors());
        }

        return $key;
    }

    /**
     * @throws \RuntimeException
     */
    private function exportKey(\OpenSSLAsymmetricKey $key, string $configuration): string
    {
        if (! openssl_pkey_export($key, $pem, null, ['config' => $configuration])) {
            throw new \RuntimeException('Cannot export a key: ' . self::errors());
        }

        return $pem;
    }

    /**
     * @throws \RuntimeException
     */
    private function sign(
        string $commonName,
        \OpenSSLAsymmetricKey $key,
        ?string $issuerCertificate,
        \OpenSSLAsymmetricKey $issuerKey,
        int $days,
        string $configuration,
        string $extensions,
    ): string {
        $options = ['config' => $configuration, 'digest_alg' => self::DIGEST];
        $request = openssl_csr_new(['commonName' => $commonName], $key, $options);
        if (! $request instanceof \OpenSSLCertificateSigningRequest) {
            throw new \RuntimeException('Cannot create a certificate request: ' . self::errors());
        }
        $certificate = openssl_csr_sign(
            $request,
            $issuerCertificate,
            $issuerKey,
            $days,
            $options + ['x509_extensions' => $extensions],
            random_int(1, PHP_INT_MAX)
        );
        if ($certificate === false || ! openssl_x509_export($certificate, $pem)) {
            throw new \RuntimeException('Cannot sign a certificate: ' . self::errors());
        }

        return $pem;
    }

    private static function errors(): string
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return implode('; ', $errors);
    }
}
