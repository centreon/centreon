<?php

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration;

use Webmozart\Assert\Assert;

abstract class AbstractConfigurationParameters
{
    protected const MAX_LENGTH = 255;
    protected const CERTIFICATE_BASE_PATH = '/etc/pki/';
    /** @var array<string> */
    protected const FORBIDDEN_DIRECTORIES = [
        '/tmp', '/root', '/proc', '/mnt', '/run', '/snap', '/sys', '/boot',
    ];

    /**
     * @param array<string,mixed> $parameters
     */
    public function __construct(protected array $parameters)
    {
    }

    /**
     * @return array<string,mixed>
     */
    abstract public function getData(): array;

    abstract public function getBrokerDirective(): ?string;

    protected function validateCertificatePath(?string $path, string $field): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $this->assertPathSecurity($path, $field);
        $normalizedPath = $this->prependPrefix($path);
        Assert::maxLength($normalizedPath, static::MAX_LENGTH, $field);

        return $normalizedPath;
    }

    protected function prependPrefix(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return static::CERTIFICATE_BASE_PATH . ltrim($path, '/');
    }

    /**
     * @throws AssertionException
     */
    protected function assertPathSecurity(string $path, string $field): void
    {
        // Reject relative path patterns
        Assert::false(
            str_contains($path, '../')
            || str_contains($path, '//')
            || str_contains($path, './')
            || $path === '.'
            || $path === '..',
            sprintf('[%s] The path "%s" contains invalid relative path patterns', $field, $path)
        );

        // Reject hidden directories
        Assert::false(
            (bool) preg_match('#/\\.#', $path)
            || (str_starts_with($path, '.') && ! str_starts_with($path, './')),
            sprintf('[%s] The path "%s" cannot be in a hidden directory', $field, $path)
        );

        // Reject forbidden directories
        foreach (static::FORBIDDEN_DIRECTORIES as $forbiddenDirectory) {
            Assert::false(
                str_starts_with($path, $forbiddenDirectory . '/') || $path === $forbiddenDirectory,
                sprintf('[%s] The path "%s" cannot be in directory %s', $field, $path, $forbiddenDirectory)
            );
        }

        // Reject /etc except /etc/pki
        if (str_starts_with($path, '/etc/')) {
            Assert::true(
                str_starts_with($path, static::CERTIFICATE_BASE_PATH) || $path === static::CERTIFICATE_BASE_PATH,
                sprintf('[%s] The path "%s" can only be in /etc/pki/ directory', $field, $path)
            );
        }
    }
}
