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

namespace Core\Security\ProviderConfiguration\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;

/**
 * This class is designed to represent the Authentication Conditions to be able to connect with OpenID Provider
 * Conditions are gathered from the Response (attribute path) of an endpoint defined by the user.
 * e.g : "http://myprovider.com/my_authorizations" will return a response:.
 *
 * {
 *   "infos": {
 *      "groups": [
 *          "groupA",
 *          "groupB"
 *      ]
 *   }
 * }
 *
 * If we want to allow access for user with groupA, we should set attributePath to "infos.groups", and authorizedValues
 * with ["groupA"]
 */
class AuthenticationConditions
{
    /** @var string[] */
    private array $trustedClientAddresses = [];

    /** @var string[] */
    private array $blacklistClientAddresses = [];

    /**
     * @param bool $isEnabled
     * @param string $attributePath
     * @param Endpoint|null $endpoint
     * @param string[] $authorizedValues
     *
     * @throws ConfigurationException
     */
    public function __construct(
        private bool $isEnabled,
        private string $attributePath,
        private ?Endpoint $endpoint,
        private array $authorizedValues
    ) {
        $this->validateMandatoryParametersForEnabledCondition(
            $isEnabled,
            $attributePath,
            $authorizedValues
        );
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    /**
     * @return string
     */
    public function getAttributePath(): string
    {
        return $this->attributePath;
    }

    /**
     * @return Endpoint|null
     */
    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    /**
     * @return string[]
     */
    public function getAuthorizedValues(): array
    {
        return $this->authorizedValues;
    }

    /**
     * @return string[]
     */
    public function getTrustedClientAddresses(): array
    {
        return $this->trustedClientAddresses;
    }

    /**
     * @return string[]
     */
    public function getBlacklistClientAddresses(): array
    {
        return $this->blacklistClientAddresses;
    }

    /**
     * @param string[] $trustedClientAddresses
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setTrustedClientAddresses(array $trustedClientAddresses): self
    {
        $this->trustedClientAddresses = [];
        foreach ($trustedClientAddresses as $trustedClientAddress) {
            $this->addTrustedClientAddress($trustedClientAddress);
        }

        return $this;
    }

    /**
     * @param string $trustedClientAddress
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function addTrustedClientAddress(string $trustedClientAddress): self
    {
        $this->validateClientAddressOrFail($trustedClientAddress, 'trustedClientAddresses');
        $this->trustedClientAddresses[] = $trustedClientAddress;

        return $this;
    }

    /**
     * @param string[] $blacklistClientAddresses
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setBlacklistClientAddresses(array $blacklistClientAddresses): self
    {
        $this->blacklistClientAddresses = [];
        foreach ($blacklistClientAddresses as $blacklistClientAddress) {
            $this->addBlacklistClientAddress($blacklistClientAddress);
        }

        return $this;
    }

    /**
     * @param string $blacklistClientAddress
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function addBlacklistClientAddress(string $blacklistClientAddress): self
    {
        $this->validateClientAddressOrFail($blacklistClientAddress, 'blacklistClientAddresses');
        $this->blacklistClientAddresses[] = $blacklistClientAddress;

        return $this;
    }

    /**
     * @param string $clientAddress
     * @param string $fieldName
     *
     * @throws \Assert\AssertionFailedException
     */
    private function validateClientAddressOrFail(string $clientAddress, string $fieldName): void
    {
        Assertion::ipOrDomain($clientAddress, 'AuthenticationConditions::' . $fieldName);
    }

    /**
     * Validate that all mandatory parameters are correctly set when conditions are enabled.
     *
     * @param bool $isEnabled
     * @param string $attributePath
     * @param string[] $authorizedValues
     *
     * @throws ConfigurationException
     */
    private function validateMandatoryParametersForEnabledCondition(
        bool $isEnabled,
        string $attributePath,
        array $authorizedValues
    ): void {
        if ($isEnabled) {
            $mandatoryParameters = [];
            if (empty($attributePath)) {
                $mandatoryParameters[] = 'attribute_path';
            }
            if ($authorizedValues === []) {
                $mandatoryParameters[] = 'authorized_values';
            }
            if (! empty($mandatoryParameters)) {
                throw ConfigurationException::missingMandatoryParameters($mandatoryParameters);
            }
        }
    }
}
