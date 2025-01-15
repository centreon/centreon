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

namespace Core\Common\Infrastructure;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Application\FeatureFlagsInterface;
use function is_int;
use function is_string;

/**
 * {@see FeatureFlagsInterface}.
 *
 * Example of the file structure.
 * <pre>{
 *   "feature-1": 0,
 *   "feature-2": 3
 * }</pre>
 */
final class FeatureFlags implements FeatureFlagsInterface
{
    use LoggerTrait;
    private const BIT_ON_PREM = 0b0001;
    private const BIT_CLOUD = 0b0010;

    /** @var array<string, bool> */
    private readonly array $flags;

    /** @var array<string> */
    private readonly array $enabledFlags;

    private readonly bool $isCloudPlatform;

    /**
     * @param bool $isCloudPlatform
     * @param string $json
     */
    public function __construct(bool $isCloudPlatform, string $json)
    {
        [$flags, $enabledFlags] = $this->prepare($isCloudPlatform, $json);

        $this->flags = $flags;
        $this->enabledFlags = $enabledFlags;
        $this->isCloudPlatform = $isCloudPlatform;
    }

    /**
     * {@inheritDoc}
     */
    public function isCloudPlatform(): bool
    {
        return $this->isCloudPlatform;
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(): array
    {
        return $this->flags;
    }

    /**
     * Return an array of flag names which are enabled (active).
     *
     * @return array<string>
     */
    public function getEnabled(): array
    {
        return $this->enabledFlags;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(string $feature): bool
    {
        if (isset($this->flags[$feature])) {
            return $this->flags[$feature];
        }

        $this->info("The feature flag '{$feature}' does not exists.");

        return false;
    }

    /**
     * This build the flags for the current platform.
     *
     * @param bool $isCloudPlatform
     * @param string $json
     *
     * @return array{
     *     array<string, bool>,
     *     array<string>
     * }
     */
    private function prepare(bool $isCloudPlatform, string $json): array
    {
        $bit = $isCloudPlatform
            ? self::BIT_CLOUD
            : self::BIT_ON_PREM;

        $flags = [];
        $enabledFlags = [];
        foreach ($this->safeJsonDecode($json) as $feature => $bitmask) {
            if (! is_string($feature) || ! is_int($bitmask)) {
                continue;
            }

            $isEnabled = (bool) ($bitmask & $bit);
            if ($isEnabled) {
                $enabledFlags[] = $feature;
            }
            $flags[$feature] = $isEnabled;
        }

        return [$flags, $enabledFlags];
    }

    /**
     * This safely decodes the JSON to an array.
     *
     * @param string $json
     *
     * @return array<mixed>
     */
    private function safeJsonDecode(string $json): array
    {
        try {
            if (is_array($array = json_decode($json, true, 10, JSON_THROW_ON_ERROR))) {
                return $array;
            }
        } catch (\JsonException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }

        return [];
    }
}
