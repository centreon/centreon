<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *  For more information : contact@centreon.com
 */

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Domain\SecurityAccess;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Domain\Exception\AuthenticationConditionsException;
use Core\Security\Authentication\Domain\Exception\SSOAuthenticationException;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath\AttributePathFetcher;

/**
 * Configured conditions must be satisfied to be authorized
 *
 * @see Conditions::validate()
 */
class Conditions implements SecurityAccessInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly LoginLoggerInterface $loginLogger,
        private readonly AttributePathFetcher $attributePathFetcher
    ) {
    }

    /**
     * @param Configuration $configuration
     * @param array<string,mixed> $identityProviderData
     * @return void
     * @throws AuthenticationConditionsException
     */
    public function validate(Configuration $configuration, array $identityProviderData): void
    {
        $scope = $configuration->getType();
        $customConfiguration = $configuration->getCustomConfiguration();
        $authenticationConditions = $customConfiguration->getAuthenticationConditions();
        if (!$authenticationConditions->isEnabled()) {
            $this->loginLogger->info($scope, "Authentication conditions disabled");
            $this->info("Authentication conditions disabled");
            return;
        }

        $this->loginLogger->info($scope, "Authentication conditions is enabled");
        $this->info("Authentication conditions is enabled");

        $customConfiguration = $configuration->getCustomConfiguration();
        $conditionsConfiguration = $customConfiguration->getAuthenticationConditions();
        $localConditions = $conditionsConfiguration->getAuthorizedValues();
        $authenticationAttributePath = explode(".", $conditionsConfiguration->getAttributePath());

        $this->loginLogger->info($scope, "Configured attribute path found", $authenticationAttributePath);
        $this->loginLogger->info($scope, "Configured authorized values", $localConditions);

        foreach ($authenticationAttributePath as $attribute) {
            $providerAuthenticationConditions = [];
            if (array_key_exists($attribute, $identityProviderData)) {
                $providerAuthenticationConditions = $identityProviderData[$attribute];
                $identityProviderData[] = $identityProviderData[$attribute];
            } else {
                break;
            }
        }

        if (is_string($providerAuthenticationConditions)) {
            $providerAuthenticationConditions = explode(",", $providerAuthenticationConditions);
        }

        if (array_is_list($providerAuthenticationConditions) === false) {
            $errorMessage = "Invalid authentication conditions format, array of strings expected";
            $this->error(
                $errorMessage,
                [
                    "authentication_condition_from_provider" => $providerAuthenticationConditions
                ]
            );

            $this->loginLogger->exception(
                $scope,
                $errorMessage,
                AuthenticationConditionsException::invalidAuthenticationConditions()
            );

            throw AuthenticationConditionsException::invalidAuthenticationConditions();
        }

        $conditionMatches = array_intersect($providerAuthenticationConditions, $localConditions);
        if (empty($conditionMatches)) {
            $this->error(
                "Configured attribute value not found in conditions endpoint",
                [
                    "configured_authorized_values" => $conditionsConfiguration->getAuthorizedValues()
                ]
            );

            $this->loginLogger->exception(
                $scope,
                "Configured attribute value not found in conditions endpoint: %s, message: %s",
                AuthenticationConditionsException::conditionsNotFound()
            );
            throw AuthenticationConditionsException::conditionsNotFound();
        }
        $this->info("Conditions found", ["conditions" => $conditionMatches]);
        $this->loginLogger->info($scope, "Conditions found", $conditionMatches);
    }
}
