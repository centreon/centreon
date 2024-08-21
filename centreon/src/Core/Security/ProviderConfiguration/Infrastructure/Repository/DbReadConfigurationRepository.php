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

namespace Core\Security\ProviderConfiguration\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\ReadOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Application\Repository\ReadConfigurationRepositoryInterface;
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
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration as SAMLCustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\WebSSO\Model\CustomConfiguration as WebSSOCustomConfiguration;

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
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     * @param ReadOpenIdConfigurationRepositoryInterface $readOpenIdConfigurationRepository
     */
    public function __construct(
        DatabaseConnection $db,
        private ReadOpenIdConfigurationRepositoryInterface $readOpenIdConfigurationRepository
    ) {
        $this->db = $db;
    }

    /**
     * @param string $providerType
     *
     * @throws ConfigurationException
     * @throws RepositoryException
     * @throws \Throwable
     *
     * @return Configuration
     */
    public function getConfigurationByType(string $providerType): Configuration
    {
        $configuration = $this->loadConfigurationByType($providerType);
        $customConfiguration = $this->loadCustomConfigurationFromConfiguration($configuration);
        $configuration->setCustomConfiguration($customConfiguration);

        return $configuration;
    }

    /**
     * @param int $id
     *
     * @throws ConfigurationException
     * @throws RepositoryException
     * @throws \Throwable
     *
     * @return Configuration
     */
    public function getConfigurationById(int $id): Configuration
    {
        $configuration = $this->loadConfigurationById($id);
        $customConfiguration = $this->loadCustomConfigurationFromConfiguration($configuration);

        $configuration->setCustomConfiguration($customConfiguration);

        return $configuration;
    }

    /**
     * @throws \Throwable
     *
     * @return Configuration[]
     */
    public function findConfigurations(): array
    {
        $configurations = [];
        $query = $this->translateDbName('SELECT name FROM `:db`.`provider_configuration`');
        $statement = $this->db->query($query) ?: null;
        while ($result = $statement?->fetch(\PDO::FETCH_ASSOC)) {
            $configurations[] = $this->getConfigurationByType($result['name']);
        }

        return $configurations;
    }

    /**
     * Find excluded users from password expiration.
     *
     * @return array<string,mixed>
     */
    public function findExcludedUsers(): array
    {
        $statement = $this->db->query(
            $this->translateDbName(
                "SELECT c.`contact_alias`
                FROM `:db`.`password_expiration_excluded_users` peeu
                INNER JOIN `:db`.`provider_configuration` pc ON pc.`id` = peeu.`provider_configuration_id`
                AND pc.`name` = 'local'
                INNER JOIN `:db`.`contact` c ON c.`contact_id` = peeu.`user_id`
                AND c.`contact_register` = 1"
            )
        );
        if ($statement !== false && $rows = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
            return $rows;
        }

        return [];
    }

    /**
     * @param Configuration $configuration
     *
     * @throws ConfigurationException
     * @throws RepositoryException|\Throwable
     *
     * @return CustomConfigurationInterface
     */
    private function loadCustomConfigurationFromConfiguration(
        Configuration $configuration
    ): CustomConfigurationInterface {

        switch ($configuration->getType()) {
            case Provider::LOCAL:
                $jsonSchemaValidatorFile = __DIR__ . '/../Local/Repository/CustomConfigurationSchema.json';
                $this->validateJsonRecord($configuration->getJsonCustomConfiguration(), $jsonSchemaValidatorFile);
                $excludedUserAliases = array_map(
                    fn($user) => $user['contact_alias'],
                    $this->findExcludedUsers()
                );

                $json = json_decode($configuration->getJsonCustomConfiguration(), true);
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

                return new LocalCustomConfiguration($securityPolicy);
            case Provider::OPENID:
                $jsonSchemaValidatorFile = __DIR__ . '/../OpenId/Repository/CustomConfigurationSchema.json';
                $json = $configuration->getJsonCustomConfiguration();
                $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
                /** @var array{
                 *     contact_template_id: int|null,
                 *     authentication_conditions: _authenticationConditionsRecord,
                 *     groups_mapping: _groupsMappingRecord,
                 *     roles_mapping: _rolesMapping
                 * } $jsonDecoded
                 */
                $jsonDecoded = json_decode($json, true);
                $jsonDecoded['contact_template'] = $jsonDecoded['contact_template_id'] !== null
                    ? $this->readOpenIdConfigurationRepository->getContactTemplate($jsonDecoded['contact_template_id'])
                    : null;
                $jsonDecoded['roles_mapping'] = $this->createAclConditions(
                    $configuration->getId(),
                    $jsonDecoded['roles_mapping']
                );
                $jsonDecoded['authentication_conditions'] = $this->createAuthenticationConditionsFromRecord(
                    $jsonDecoded['authentication_conditions']
                );
                $jsonDecoded['groups_mapping'] = $this->createGroupsMappingFromRecord(
                    $jsonDecoded['groups_mapping'],
                    $configuration->getId()
                );

                return new OpenIdCustomConfiguration($jsonDecoded);
            case Provider::WEB_SSO:
                $jsonSchemaValidatorFile = __DIR__ . '/../WebSSO/Repository/CustomConfigurationSchema.json';
                $json = $configuration->getJsonCustomConfiguration();
                $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
                $json = json_decode($json, true);

                return new WebSSOCustomConfiguration(
                    $json['trusted_client_addresses'],
                    $json['blacklist_client_addresses'],
                    $json['login_header_attribute'],
                    $json['pattern_matching_login'],
                    $json['pattern_replace_login']
                );

            case Provider::SAML:
                $jsonSchemaValidatorFile = __DIR__ . '/../SAML/Repository/CustomConfigurationSchema.json';
                $json = $configuration->getJsonCustomConfiguration();
                $this->validateJsonRecord($json, $jsonSchemaValidatorFile);
                $jsonDecoded = json_decode($json, true);
                $jsonDecoded['contact_template'] = $jsonDecoded['contact_template_id'] !== null
                    ? $this->readOpenIdConfigurationRepository->getContactTemplate($jsonDecoded['contact_template_id'])
                    : null;
                $jsonDecoded['roles_mapping'] = $this->createAclConditions(
                    $configuration->getId(),
                    $jsonDecoded['roles_mapping']
                );
                $jsonDecoded['authentication_conditions'] = $this->createAuthenticationConditionsFromRecord(
                    $jsonDecoded['authentication_conditions']
                );
                $jsonDecoded['groups_mapping'] = $this->createGroupsMappingFromRecord(
                    $jsonDecoded['groups_mapping'],
                    $configuration->getId()
                );

                return new SAMLCustomConfiguration($jsonDecoded);
            default:
                throw new \Exception("Unknown provider configuration name, can't load custom configuration");
        }
    }

    /**
     * @param int $configurationId
     * @param _rolesMapping $rolesMapping
     *
     * @throws ACLConditionsException
     * @throws InvalidEndpointException
     *
     * @return ACLConditions
     */
    private function createAclConditions(int $configurationId, array $rolesMapping): ACLConditions
    {
        $rules = $this->readOpenIdConfigurationRepository->getAuthorizationRulesByConfigurationId($configurationId);
        $endpoint = null;
        if (array_key_exists('endpoint', $rolesMapping)) {
            $endpoint = new Endpoint($rolesMapping['endpoint']['type'], $rolesMapping['endpoint']['custom_endpoint']);
        }

        return new ACLConditions(
            $rolesMapping['is_enabled'],
            $rolesMapping['apply_only_first_role'],
            $rolesMapping['attribute_path'],
            $endpoint,
            $rules
        );
    }

    /**
     * @param string $providerName
     *
     * @throws \Exception
     *
     * @return Configuration
     */
    private function loadConfigurationByType(string $providerName): Configuration
    {
        $query = $this->translateDbName(
            sprintf("SELECT *
                FROM `:db`.`provider_configuration`
                WHERE `type` = '%s'", $providerName)
        );

        $statement = $this->db->query($query) ?: null;
        if ($result = $statement?->fetch(\PDO::FETCH_ASSOC)) {
            return new Configuration(
                (int) $result['id'],
                $result['type'],
                $result['name'],
                $result['custom_configuration'],
                (bool) $result['is_active'],
                (bool) $result['is_forced']
            );
        }

        throw new \Exception(sprintf('Provider configuration with name %s not found', $providerName));
    }

    /**
     * @param int $id
     *
     * @throws \Exception
     *
     * @return Configuration
     */
    private function loadConfigurationById(int $id): Configuration
    {
        $query = $this->translateDbName(
            sprintf('SELECT *
                FROM `:db`.`provider_configuration`
                WHERE `id` = %d', $id)
        );

        $statement = $this->db->query($query) ?: null;
        if ($result = $statement?->fetch(\PDO::FETCH_ASSOC)) {
            return new Configuration(
                (int) $result['id'],
                $result['type'],
                $result['name'],
                $result['custom_configuration'],
                (bool) $result['is_active'],
                (bool) $result['is_forced']
            );
        }

        throw new \Exception(sprintf('provider configuration with id %d not found', $id));
    }

    /**
     * Create Authentication Conditions from record.
     *
     * @param _authenticationConditionsRecord $authenticationConditionsRecord
     *
     * @return AuthenticationConditions
     */
    private function createAuthenticationConditionsFromRecord(
        array $authenticationConditionsRecord
    ): AuthenticationConditions {

        $endpoint = null;
        if (array_key_exists('endpoint', $authenticationConditionsRecord)) {
            $endpoint = new Endpoint(
                $authenticationConditionsRecord['endpoint']['type'],
                $authenticationConditionsRecord['endpoint']['custom_endpoint']
            );
        }

        $authenticationConditions = new AuthenticationConditions(
            $authenticationConditionsRecord['is_enabled'],
            $authenticationConditionsRecord['attribute_path'],
            $endpoint,
            $authenticationConditionsRecord['authorized_values']
        );

        if (! empty($authenticationConditionsRecord['trusted_client_addresses'])) {
            $authenticationConditions->setTrustedClientAddresses(
                $authenticationConditionsRecord['trusted_client_addresses']
            );
        }

        if (! empty($authenticationConditionsRecord['blacklist_client_addresses'])) {
            $authenticationConditions->setBlacklistClientAddresses(
                $authenticationConditionsRecord['blacklist_client_addresses']
            );
        }

        return $authenticationConditions;
    }

    /**
     * Create Groups Mapping From Record.
     *
     * @param _groupsMappingRecord $groupsMappingRecord
     * @param int $configurationId
     *
     * @return GroupsMapping
     */
    private function createGroupsMappingFromRecord(array $groupsMappingRecord, int $configurationId): GroupsMapping
    {
        $endpoint = null;
        if (array_key_exists('endpoint', $groupsMappingRecord)) {
            $endpoint = new Endpoint(
                $groupsMappingRecord['endpoint']['type'],
                $groupsMappingRecord['endpoint']['custom_endpoint']
            );
        }

        $contactGroupRelations = $this->readOpenIdConfigurationRepository->getContactGroupRelationsByConfigurationId(
            $configurationId
        );

        return new GroupsMapping(
            $groupsMappingRecord['is_enabled'],
            $groupsMappingRecord['attribute_path'],
            $endpoint,
            $contactGroupRelations
        );
    }
}
