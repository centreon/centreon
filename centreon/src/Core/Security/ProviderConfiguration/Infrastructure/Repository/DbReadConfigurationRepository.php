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

namespace Core\Security\ProviderConfiguration\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Repository\RepositoryException as LegacyRepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\ReadOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\ReadSAMLConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Exception\InvalidEndpointException;
use Core\Security\ProviderConfiguration\Domain\Local\Model\CustomConfiguration as LocalCustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\Local\Model\SecurityPolicy;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\ACLConditionsException;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration as OpenIdCustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\SAML\Exception\MissingLogoutUrlException;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration as SAMLCustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\WebSSO\Model\CustomConfiguration as WebSSOCustomConfiguration;
use JsonSchema\Exception\InvalidArgumentException;

/**
 * @phpstan-import-type _EndpointArray from Endpoint
 *
 * @phpstan-type _authenticationConditionsRecord array{
 *     is_enabled: bool,
 *     attribute_path: string,
 *     authorized_values: string[],
 *     trusted_client_addresses: string[],
 *     blacklist_client_addresses: string[],
 *     endpoint: _EndpointArray
 * }
 * @phpstan-type _groupsMappingRecord array{
 *     is_enabled: bool,
 *     attribute_path: string,
 *     endpoint: _EndpointArray
 * }
 * @phpstan-type _rolesMapping array{
 *     is_enabled: bool,
 *     apply_only_first_role: bool,
 *     attribute_path: string,
 *     endpoint?: _EndpointArray
 * }
 */
final class DbReadConfigurationRepository extends AbstractRepositoryDRB implements ReadConfigurationRepositoryInterface
{
    public function __construct(
        DatabaseConnection $db,
        private readonly ReadOpenIdConfigurationRepositoryInterface $readOpenIdConfigurationRepository,
        private readonly ReadSAMLConfigurationRepositoryInterface $readSamlConfigurationRepository,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationByType(string $providerType): Configuration
    {
        $configuration = $this->loadConfigurationByType($providerType);
        $customConfiguration = $this->loadCustomConfigurationFromConfiguration($configuration);
        $configuration->setCustomConfiguration($customConfiguration);

        return $configuration;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationById(int $id): Configuration
    {
        $configuration = $this->loadConfigurationById($id);
        $customConfiguration = $this->loadCustomConfigurationFromConfiguration($configuration);

        $configuration->setCustomConfiguration($customConfiguration);

        return $configuration;
    }

    /**
     * @inheritDoc
     */
    public function findConfigurations(): array
    {
        $query = <<<'SQL'
            SELECT name
            FROM `:db`.`provider_configuration`
            SQL;

        try {
            $entries = $this->db->fetchAllAssociative($this->translateDbName($query));
        } catch (ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch provider configurations count from database',
                previous: $e
            );
        }

        $configurations = [];
        foreach ($entries as $entry) {
            $configurations[] = $this->getConfigurationByType($entry['name']);
        }

        return $configurations;
    }

    /**
     * @inheritDoc
     */
    public function findExcludedUsers(): array
    {
        try {
            $query = <<<'SQL'
                SELECT c.`contact_alias`
                FROM `:db`.`password_expiration_excluded_users` peeu
                INNER JOIN `:db`.`provider_configuration` pc ON pc.`id` = peeu.`provider_configuration_id`
                AND pc.`name` = 'local'
                INNER JOIN `:db`.`contact` c ON c.`contact_id` = peeu.`user_id`
                AND c.`contact_register` = :contactRegister
                SQL;

            $queryParameters = QueryParameters::create([QueryParameter::int('contactRegister', 1)]);

            return $this->db->fetchAllAssociative($this->translateDbName($query), $queryParameters);
        } catch (ConnectionException|ValueObjectException|CollectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch excluded users from database',
                previous: $e
            );
        }
    }

    /**
     * @throws RepositoryException
     */
    private function loadCustomConfigurationFromConfiguration(
        Configuration $configuration,
    ): CustomConfigurationInterface {
        return match ($configuration->getType()) {
            Provider::LOCAL => $this->loadLocalCustomConfiguration($configuration),
            Provider::OPENID => $this->loadOpenIdCustomConfiguration($configuration),
            Provider::WEB_SSO => $this->loadWebSsoCustomConfiguration($configuration),
            Provider::SAML => $this->loadSamlCustomConfiguration($configuration),
            default => throw new RepositoryException(
                message: "Unknown provider configuration type named {$configuration->getType()}, can't load custom configuration",
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                    'provider_configuration_name' => $configuration->getName(),
                ]
            ),
        };
    }

    /**
     * @throws RepositoryException
     */
    private function loadSamlCustomConfiguration(Configuration $configuration): SAMLCustomConfiguration
    {
        try {
            $jsonSchemaValidatorFile = __DIR__ . '/../SAML/Repository/CustomConfigurationSchema.json';
            $json = $configuration->getJsonCustomConfiguration();
            $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
        } catch (LegacyRepositoryException|InvalidArgumentException $e) {
            throw new RepositoryException(
                message: 'Could not validate json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        try {
            $jsonDecoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RepositoryException(
                message: 'Could not decode SAML provider configuration from json',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        $jsonDecoded['contact_template'] = $jsonDecoded['contact_template_id'] !== null
            ? $this->readSamlConfigurationRepository->findOneContactTemplate(
                $jsonDecoded['contact_template_id']
            )
            : null;

        $jsonDecoded['roles_mapping'] = $this->createAclConditions(
            Provider::SAML,
            $configuration->getId(),
            $jsonDecoded['roles_mapping']
        );

        $jsonDecoded['authentication_conditions'] = $this->createAuthenticationConditionsFromRecord(
            $jsonDecoded['authentication_conditions']
        );

        $jsonDecoded['groups_mapping'] = $this->createGroupsMappingFromRecord(
            Provider::SAML,
            $jsonDecoded['groups_mapping'],
            $configuration->getId()
        );

        try {
            return SAMLCustomConfiguration::createFromValues($jsonDecoded);
        } catch (ConfigurationException|MissingLogoutUrlException $e) {
            throw new RepositoryException(
                message: 'Could not create SAML custom configuration from json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                    'json_decoded' => $jsonDecoded,
                ],
                previous: $e
            );
        }
    }

    /**
     * @throws RepositoryException
     */
    private function loadWebSsoCustomConfiguration(Configuration $configuration): WebSSOCustomConfiguration
    {
        try {
            $jsonSchemaValidatorFile = __DIR__ . '/../WebSSO/Repository/CustomConfigurationSchema.json';
            $json = $configuration->getJsonCustomConfiguration();
            $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
        } catch (LegacyRepositoryException|InvalidArgumentException $e) {
            throw new RepositoryException(
                message: 'Could not validate json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        try {
            $json = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RepositoryException(
                message: 'Could not decode WebSSO provider configuration from json',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        return new WebSSOCustomConfiguration(
            $json['trusted_client_addresses'],
            $json['blacklist_client_addresses'],
            $json['login_header_attribute'],
            $json['pattern_matching_login'],
            $json['pattern_replace_login']
        );
    }

    /**
     * @throws RepositoryException
     */
    private function loadOpenIdCustomConfiguration(Configuration $configuration): OpenIdCustomConfiguration
    {
        try {
            $jsonSchemaValidatorFile = __DIR__ . '/../OpenId/Repository/CustomConfigurationSchema.json';
            $json = $configuration->getJsonCustomConfiguration();
            $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
        } catch (LegacyRepositoryException|InvalidArgumentException $e) {
            throw new RepositoryException(
                message: 'Could not validate json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        try {
            /** @var array{
             *     contact_template_id: int|null,
             *     authentication_conditions: _authenticationConditionsRecord,
             *     groups_mapping: _groupsMappingRecord,
             *     roles_mapping: _rolesMapping
             * } $jsonDecoded
             */
            $jsonDecoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RepositoryException(
                message: 'Could not decode OpenID provider configuration from json',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        $jsonDecoded['contact_template'] = $jsonDecoded['contact_template_id'] !== null
            ? $this->readOpenIdConfigurationRepository->findOneContactTemplate($jsonDecoded['contact_template_id'])
            : null;

        $jsonDecoded['roles_mapping'] = $this->createAclConditions(
            Provider::OPENID,
            $configuration->getId(),
            $jsonDecoded['roles_mapping']
        );

        $jsonDecoded['authentication_conditions'] = $this->createAuthenticationConditionsFromRecord(
            $jsonDecoded['authentication_conditions']
        );

        $jsonDecoded['groups_mapping'] = $this->createGroupsMappingFromRecord(
            Provider::OPENID,
            $jsonDecoded['groups_mapping'],
            $configuration->getId()
        );

        try {
            return new OpenIdCustomConfiguration($jsonDecoded);
        } catch (ConfigurationException $e) {
            throw new RepositoryException(
                message: 'Could not create OpenID custom configuration from json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }
    }

    /**
     * @throws RepositoryException
     */
    private function loadLocalCustomConfiguration(Configuration $configuration): LocalCustomConfiguration
    {
        try {
            $jsonSchemaValidatorFile = __DIR__ . '/../Local/Repository/CustomConfigurationSchema.json';
            $this->validateJsonRecord($configuration->getJsonCustomConfiguration(), $jsonSchemaValidatorFile);
        } catch (LegacyRepositoryException|InvalidArgumentException $e) {
            throw new RepositoryException(
                message: 'Could not validate json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        $excludedUserAliases = array_map(
            fn ($user) => $user['contact_alias'],
            $this->findExcludedUsers()
        );

        try {
            $json = json_decode($configuration->getJsonCustomConfiguration(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RepositoryException(
                message: 'Could not decode Local provider configuration from json',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        try {
            $securityPolicy = new SecurityPolicy(
                $json['password_security_policy']['password_length'],
                $json['password_security_policy']['has_uppercase_characters'],
                $json['password_security_policy']['has_lowercase_characters'],
                $json['password_security_policy']['has_numbers'],
                $json['password_security_policy']['has_special_characters'],
                $json['password_security_policy']['can_reuse_passwords'],
                $json['password_security_policy']['attempts'],
                $json['password_security_policy']['blocking_duration'],
                $json['password_security_policy']['password_expiration_delay'],
                $excludedUserAliases,
                $json['password_security_policy']['delay_before_new_password'],
            );
        } catch (AssertionException $e) {
            throw new RepositoryException(
                message: 'Could not create security policy from json record',
                context: [
                    'provider_configuration_id' => $configuration->getId(),
                    'provider_configuration_type' => $configuration->getType(),
                ],
                previous: $e
            );
        }

        return new LocalCustomConfiguration($securityPolicy);
    }

    /**
     * @param _rolesMapping $rolesMapping
     *
     * @throws RepositoryException
     */
    private function createAclConditions(string $providerName, int $configurationId, array $rolesMapping): ACLConditions
    {
        $rules = [];
        match ($providerName) {
            Provider::OPENID => $rules = $this->readOpenIdConfigurationRepository->findAuthorizationRulesByConfigurationId(
                $configurationId
            ),
            Provider::SAML => $rules = $this->readSamlConfigurationRepository->findAuthorizationRulesByConfigurationId(
                $configurationId
            ),
            default => throw new RepositoryException(
                message: 'ACL Conditions can only be created for OpenID or SAML providers.',
                context: [
                    'provider_name' => $providerName,
                    'provider_configuration_id' => $configurationId,
                ]
            ),
        };

        $endpoint = null;
        if (array_key_exists('endpoint', $rolesMapping)) {
            try {
                $endpoint = new Endpoint(
                    $rolesMapping['endpoint']['type'],
                    $rolesMapping['endpoint']['custom_endpoint']
                );
            } catch (InvalidEndpointException $e) {
                throw new RepositoryException(
                    message: 'Could not create endpoint for ACL conditions from roles mapping',
                    context: [
                        'provider_name' => $providerName,
                        'provider_configuration_id' => $configurationId,
                        'endpoint_type' => $rolesMapping['endpoint']['type'],
                        'custom_endpoint' => $rolesMapping['endpoint']['custom_endpoint'],
                    ],
                    previous: $e
                );
            }
        }

        try {
            return new ACLConditions(
                $rolesMapping['is_enabled'],
                $rolesMapping['apply_only_first_role'],
                $rolesMapping['attribute_path'],
                $endpoint,
                $rules
            );
        } catch (ACLConditionsException $e) {
            throw new RepositoryException(
                message: 'Could not create ACL conditions',
                context: [
                    'provider_name' => $providerName,
                    'provider_configuration_id' => $configurationId,
                    'is_enabled' => $rolesMapping['is_enabled'],
                    'apply_only_first_role' => $rolesMapping['apply_only_first_role'],
                    'attribute_path' => $rolesMapping['attribute_path'],
                    'endpoint' => $endpoint,
                    'authorization_rules' => $rules,
                ],
                previous: $e
            );
        }
    }

    /**
     * @throws RepositoryException
     */
    private function loadConfigurationByType(string $providerName): Configuration
    {
        $query = <<<'SQL'
            SELECT *
            FROM `:db`.`provider_configuration`
            WHERE `type` = :providerName
            SQL;

        try {
            $queryParameters = QueryParameters::create([QueryParameter::string('providerName', $providerName)]);
            $entry = $this->db->fetchAssociative($this->translateDbName($query), $queryParameters);
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch provider configuration from database',
                context: ['provider_name' => $providerName],
                previous: $e
            );
        }

        if ($entry !== false) {
            return new Configuration(
                (int) $entry['id'],
                $entry['type'],
                $entry['name'],
                $entry['custom_configuration'],
                (bool) $entry['is_active'],
                (bool) $entry['is_forced']
            );
        }

        throw new RepositoryException(
            message: sprintf('Provider configuration with name %s not found', $providerName),
            context: ['provider_name' => $providerName]
        );
    }

    /**
     * @throws RepositoryException
     */
    private function loadConfigurationById(int $id): Configuration
    {
        $query = <<<'SQL'
            SELECT *
            FROM `:db`.`provider_configuration`
            WHERE `id` = :id
            SQL;

        try {
            $queryParameters = QueryParameters::create([QueryParameter::int('id', $id)]);
            $entry = $this->db->fetchAssociative($this->translateDbName($query), $queryParameters);
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch provider configuration from database',
                context: ['id_provider_configuration' => $id],
                previous: $e
            );
        }

        if ($entry !== false) {
            return new Configuration(
                (int) $entry['id'],
                $entry['type'],
                $entry['name'],
                $entry['custom_configuration'],
                (bool) $entry['is_active'],
                (bool) $entry['is_forced']
            );
        }

        throw new RepositoryException(
            message: sprintf('Provider configuration with id %d not found', $id),
            context: ['id_provider_configuration' => $id]
        );
    }

    /**
     * @param _authenticationConditionsRecord $authenticationConditionsRecord
     *
     * @throws RepositoryException
     */
    private function createAuthenticationConditionsFromRecord(
        array $authenticationConditionsRecord,
    ): AuthenticationConditions {
        $endpoint = null;
        if (array_key_exists('endpoint', $authenticationConditionsRecord)) {
            try {
                $endpoint = new Endpoint(
                    $authenticationConditionsRecord['endpoint']['type'],
                    $authenticationConditionsRecord['endpoint']['custom_endpoint']
                );
            } catch (InvalidEndpointException $e) {
                throw new RepositoryException(
                    message: 'Could not create endpoint for authentication conditions from authentication conditions record',
                    context: [
                        'endpoint_type' => $authenticationConditionsRecord['endpoint']['type'],
                        'custom_endpoint' => $authenticationConditionsRecord['endpoint']['custom_endpoint'],
                    ],
                    previous: $e
                );
            }
        }

        try {
            $authenticationConditions = new AuthenticationConditions(
                $authenticationConditionsRecord['is_enabled'],
                $authenticationConditionsRecord['attribute_path'],
                $endpoint,
                $authenticationConditionsRecord['authorized_values']
            );
        } catch (ConfigurationException $e) {
            throw new RepositoryException(
                message: 'Could not create authentication conditions',
                context: [
                    'is_enabled' => $authenticationConditionsRecord['is_enabled'],
                    'attribute_path' => $authenticationConditionsRecord['attribute_path'],
                    'authorized_values' => $authenticationConditionsRecord['authorized_values'],
                    'endpoint' => $endpoint,
                ],
                previous: $e
            );
        }

        if (! empty($authenticationConditionsRecord['trusted_client_addresses'])) {
            try {
                $authenticationConditions->setTrustedClientAddresses(
                    $authenticationConditionsRecord['trusted_client_addresses']
                );
            } catch (AssertionFailedException $e) {
                throw new RepositoryException(
                    message: 'Could not set trusted client addresses for authentication conditions',
                    context: ['trusted_client_addresses' => $authenticationConditionsRecord['trusted_client_addresses']],
                    previous: $e
                );
            }
        }

        if (! empty($authenticationConditionsRecord['blacklist_client_addresses'])) {
            try {
                $authenticationConditions->setBlacklistClientAddresses(
                    $authenticationConditionsRecord['blacklist_client_addresses']
                );
            } catch (AssertionFailedException $e) {
                throw new RepositoryException(
                    message: 'Could not set blacklist client addresses for authentication conditions',
                    context: ['blacklist_client_addresses' => $authenticationConditionsRecord['blacklist_client_addresses']],
                    previous: $e
                );
            }
        }

        return $authenticationConditions;
    }

    /**
     * @param _groupsMappingRecord $groupsMappingRecord
     *
     * @throws RepositoryException
     */
    private function createGroupsMappingFromRecord(
        string $providerName,
        array $groupsMappingRecord,
        int $configurationId,
    ): GroupsMapping {
        $endpoint = null;

        if (array_key_exists('endpoint', $groupsMappingRecord)) {
            try {
                $endpoint = new Endpoint(
                    $groupsMappingRecord['endpoint']['type'],
                    $groupsMappingRecord['endpoint']['custom_endpoint']
                );
            } catch (InvalidEndpointException $e) {
                throw new RepositoryException(
                    message: 'Could not create endpoint for groups mapping from groups mapping record',
                    context: [
                        'provider_name' => $providerName,
                        'provider_configuration_id' => $configurationId,
                        'endpoint_type' => $groupsMappingRecord['endpoint']['type'],
                        'custom_endpoint' => $groupsMappingRecord['endpoint']['custom_endpoint'],
                    ],
                    previous: $e
                );
            }
        }

        $contactGroupRelations = [];
        match ($providerName) {
            Provider::OPENID => $contactGroupRelations = $this->readOpenIdConfigurationRepository->findContactGroupRelationsByConfigurationId(
                $configurationId
            ),
            Provider::SAML => $contactGroupRelations = $this->readSamlConfigurationRepository->findContactGroupRelationsByConfigurationId(
                $configurationId
            ),
            default => throw new RepositoryException(
                message: 'Groups Mapping can only be created for OpenID or SAML providers.',
                context: [
                    'provider_name' => $providerName,
                    'provider_configuration_id' => $configurationId,
                ]
            ),
        };

        try {
            return new GroupsMapping(
                $groupsMappingRecord['is_enabled'],
                $groupsMappingRecord['attribute_path'],
                $endpoint,
                $contactGroupRelations
            );
        } catch (ConfigurationException $e) {
            throw new RepositoryException(
                message: 'Could not create groups mapping',
                context: [
                    'provider_name' => $providerName,
                    'provider_configuration_id' => $configurationId,
                    'is_enabled' => $groupsMappingRecord['is_enabled'],
                    'attribute_path' => $groupsMappingRecord['attribute_path'],
                    'endpoint' => $endpoint,
                    'contact_group_relations' => $contactGroupRelations,
                ],
                previous: $e
            );
        }
    }
}
