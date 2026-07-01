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

namespace Tests\Core\Common\Application;

use Core\Common\Application\VaultEligibilityService;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;

beforeEach(function (): void {
    $this->readVaultConfigurationRepository = $this->createMock(ReadVaultConfigurationRepositoryInterface::class);
});

it(
    'should return false when feature flag is disabled',
    function (): void {
        $featureFlags = new FeatureFlags(false, '{"vault": 0}');
        $this->readVaultConfigurationRepository->method('exists')->willReturn(true);

        $service = new VaultEligibilityService($featureFlags, $this->readVaultConfigurationRepository);

        expect($service->shouldUseVault())->toBeFalse();
    }
);

it(
    'should return false when feature flag is enabled but vault config does not exist',
    function (): void {
        $featureFlags = new FeatureFlags(false, '{"vault": 1}');
        $this->readVaultConfigurationRepository->method('exists')->willReturn(false);

        $service = new VaultEligibilityService($featureFlags, $this->readVaultConfigurationRepository);

        expect($service->shouldUseVault())->toBeFalse();
    }
);

it(
    'should return true when feature flag is enabled and vault config exists',
    function (): void {
        $featureFlags = new FeatureFlags(false, '{"vault": 1}');
        $this->readVaultConfigurationRepository->method('exists')->willReturn(true);

        $service = new VaultEligibilityService($featureFlags, $this->readVaultConfigurationRepository);

        expect($service->shouldUseVault())->toBeTrue();
    }
);

it(
    'should check the correct feature flag when a custom flag name is provided',
    function (): void {
        $featureFlags = new FeatureFlags(false, '{"vault": 0, "vault_broker": 1}');
        $this->readVaultConfigurationRepository->method('exists')->willReturn(true);

        $service = new VaultEligibilityService($featureFlags, $this->readVaultConfigurationRepository);

        expect($service->shouldUseVault())->toBeFalse();
        expect($service->shouldUseVault('vault_broker'))->toBeTrue();
    }
);
