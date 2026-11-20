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

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Log\LoggerUpgrade;

require_once __DIR__ . '/../../../bootstrap.php';

$version = 'xx.xx.x';

$errorMessage = '';

/**
 * @var ConnectionInterface $pearDB
 * @var ConnectionInterface $pearDBO
 */

// TODO add your functions here

$createAclFingerprintTable = function () use ($pearDBO, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create the centreon_acl_checksum table';
    LoggerUpgrade::create()->info($version, 'Creating the centreon_acl_checksum table');

    // Fingerprint of the centreon_acl rows of each access group: centAcl.php skips the groups whose rows did not change.
    $pearDBO->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `centreon_acl_checksum` (
                `group_id` int(11) NOT NULL,
                `checksum` char(32) NOT NULL,
                PRIMARY KEY (`group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
            SQL
    );
};

/** ------------------------------------ AI provider configurations ------------------------------------ */
$createAiProviderConfigurationTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create ai_provider_configuration table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Creating ai_provider_configuration table",
    );

    $pearDB->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ai_provider_configuration` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the AI provider configuration',
              `type` enum('anthropic','openai','mistral','google','bedrock','openai_compatible') NOT NULL COMMENT 'Type of the AI provider',
              `name` varchar(255) NOT NULL COMMENT 'Name of the AI provider configuration',
              `description` text COMMENT 'Description of the AI provider configuration',
              `is_activated` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether this provider is enabled; at most one provider is enabled and it is the one used by AI features',
              `configuration` JSON NOT NULL COMMENT 'Provider parameters: endpoint url, api token (plaintext or vault path), default and fast models',
              `created_at` int(11) NOT NULL COMMENT 'Creation timestamp',
              `updated_at` int(11) NOT NULL COMMENT 'Last update timestamp',
              PRIMARY KEY (`id`),
              UNIQUE KEY `ai_provider_configuration_name_unique` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI provider configurations for LLM-based features'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: ai_provider_configuration table created successfully",
    );
};

$createAiProviderUsageLogTable = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to create ai_provider_usage_log table';
    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: Creating ai_provider_usage_log table",
    );

    $pearDB->executeStatement(
        <<<'SQL'
            CREATE TABLE IF NOT EXISTS `ai_provider_usage_log` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for the usage log entry',
              `ai_provider_id` INT UNSIGNED DEFAULT NULL COMMENT 'AI provider configuration that served the request',
              `provider_name` varchar(255) NOT NULL COMMENT 'Name of the provider at the time of the request (snapshot)',
              `feature` VARCHAR(50) NOT NULL COMMENT 'AI feature that triggered the request (an AiFeatureEnum value)',
              `contact_id` int(11) DEFAULT NULL COMMENT 'Contact that triggered the request',
              `model` varchar(255) NOT NULL COMMENT 'Model that served the request',
              `tier` enum('default','fast') NOT NULL COMMENT 'Model tier used for the request',
              `input_tokens` INT UNSIGNED NOT NULL COMMENT 'Input (prompt) token count',
              `output_tokens` INT UNSIGNED NOT NULL COMMENT 'Output (completion) token count',
              `cost` DECIMAL(14,6) DEFAULT NULL COMMENT 'Cost of the request computed at write time; null when no pricing is configured',
              `currency` char(3) DEFAULT NULL COMMENT 'ISO 4217 currency of the cost (snapshot); null when cost is null',
              `requested_at` int(11) NOT NULL COMMENT 'Request timestamp (UTC)',
              PRIMARY KEY (`id`),
              KEY `ai_provider_usage_log_window` (`ai_provider_id`, `requested_at`),
              CONSTRAINT `fk_ai_provider_usage_log_provider`
                FOREIGN KEY (`ai_provider_id`)
                REFERENCES `ai_provider_configuration` (`id`)
                ON DELETE SET NULL,
              CONSTRAINT `fk_ai_provider_usage_log_contact`
                FOREIGN KEY (`contact_id`)
                REFERENCES `contact` (`contact_id`)
                ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Per-request token usage and cost of LLM-based features'
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: ai_provider_usage_log table created successfully",
    );
};

$addAiProvidersTopology = function () use ($pearDB, &$errorMessage, $version): void {
    $errorMessage = 'Unable to add AI Providers topology entry';

    $alreadyExists = $pearDB->fetchOne(
        <<<'SQL'
            SELECT 1 FROM `topology` WHERE `topology_page` = :topology_page
            SQL,
        QueryParameters::create([
            QueryParameter::int('topology_page', 50116),
        ])
    );
    if ($alreadyExists !== false) {
        CentreonLog::create()->info(
            logTypeId: CentreonLog::TYPE_UPGRADE,
            message: "UPGRADE - {$version}: AI Providers topology entry already exists, skipping insertion",
        );

        return;
    }

    $pearDB->executeStatement(
        <<<'SQL'
            INSERT INTO `topology`
                (`topology_name`, `topology_url`, `readonly`, `is_react`, `topology_parent`,
                 `topology_page`, `topology_order`, `topology_group`, `topology_feature_flag`)
            VALUES
                ('AI Providers', '/administration/parameters/ai-providers', '1', '1', 501,
                 50116, 110, 1, 'ai_providers')
            SQL
    );

    CentreonLog::create()->info(
        logTypeId: CentreonLog::TYPE_UPGRADE,
        message: "UPGRADE - {$version}: AI Providers topology entry added successfully",
    );
};

try {
    LoggerUpgrade::create()->info($version, "Starting upgrade script for version {$version}");

    // DDL statements for real time database
    // TODO add your function calls to update the real time database structure here
    $createAclFingerprintTable();

    // DDL statements for configuration database
    $createAiProviderConfigurationTable();
    $createAiProviderUsageLogTable();

    // Transactional queries for configuration database
    $errorMessage = 'Unable to start the configuration database transaction';
    if (! $pearDB->isTransactionActive()) {
        $pearDB->startTransaction();
    }

    $addAiProvidersTopology();

    $errorMessage = 'Unable to commit the configuration database transaction';
    $pearDB->commitTransaction();

    LoggerUpgrade::create()->info($version, "Upgrade script for version {$version} completed");

} catch (Throwable $throwable) {
    try {
        if ($pearDB->isTransactionActive()) {
            LoggerUpgrade::create()->info($version, "Rolling back transaction after error: {$errorMessage}");
            $pearDB->rollBackTransaction();
        }
    } catch (ConnectionException $rollbackException) {
        LoggerUpgrade::create()->stepFailure(
            $version,
            'php_script_rollback',
            "UPGRADE - {$version}: error while rolling back the upgrade operation for : {$errorMessage}",
            $rollbackException
        );

        throw new RuntimeException(
            message: "UPGRADE - {$version}: " . $errorMessage,
            previous: $throwable
        );
    }

    throw new RuntimeException(
        message: "UPGRADE - {$version}: " . $errorMessage,
        previous: $throwable
    );
}
