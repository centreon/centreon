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

namespace Core\HostTemplate\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\HostEvent;
use Core\Common\Domain\HostType;
use Core\Common\Domain\SnmpVersion;
use Core\Common\Domain\YesNoDefault;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Utility\SqlConcatenator;

class DbReadHostTemplateRepository extends AbstractRepositoryRDB implements ReadHostTemplateRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array
    {
        $this->info('Getting all host templates');

        $concatenator = new SqlConcatenator();
        $concatenator->withCalcFoundRows(true);
        $concatenator->defineSelect(
            <<<'SQL'
                SELECT
                    h.host_id,
                    h.host_name,
                    h.host_alias,
                    h.host_snmp_version,
                    h.host_snmp_community,
                    h.host_location,
                    h.command_command_id,
                    h.command_command_id_arg1,
                    h.timeperiod_tp_id,
                    h.host_max_check_attempts,
                    h.host_check_interval,
                    h.host_retry_check_interval,
                    h.host_active_checks_enabled,
                    h.host_passive_checks_enabled,
                    h.host_notifications_enabled,
                    h.host_notification_options,
                    h.host_notification_interval,
                    h.timeperiod_tp_id2,
                    h.cg_additive_inheritance,
                    h.contact_additive_inheritance,
                    h.host_first_notification_delay,
                    h.host_recovery_notification_delay,
                    h.host_acknowledgement_timeout,
                    h.host_check_freshness,
                    h.host_freshness_threshold,
                    h.host_flap_detection_enabled,
                    h.host_low_flap_threshold,
                    h.host_high_flap_threshold,
                    h.host_event_handler_enabled,
                    h.command_command_id2,
                    h.command_command_id_arg2,
                    h.host_comment,
                    h.host_activate,
                    h.host_locked,
                    ehi.ehi_notes_url,
                    ehi.ehi_notes,
                    ehi.ehi_action_url,
                    ehi.ehi_icon_image,
                    ehi.ehi_icon_image_alt,
                    hcr.hostcategories_hc_id as severity_id
                FROM `:db`.host h
                LEFT JOIN `:db`.extended_host_information ehi ON h.host_id = ehi.host_host_id
                LEFT JOIN `:db`.hostcategories_relation hcr ON h.host_id = hcr.host_host_id
                SQL
        );

        // Filter on host templates
        $concatenator->appendWhere('h.host_register = :hostTemplateType');
        $concatenator->storeBindValue(':hostTemplateType', HostType::Template->value);
        // Filter on host severity
        $concatenator->appendWhere(
            <<<'SQL'
                (
                    hcr.hostcategories_hc_id IS NULL
                    OR hcr.hostcategories_hc_id IN (SELECT hc_id FROM hostcategories WHERE level IS NOT NULL)
                )
                SQL
        );

        // Settup for search, pagination, order
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'h.host_id',
            'name' => 'h.host_name',
            'alias' => 'h.host_alias',
            'is_activated' => 'h.host_register',
            'is_locked' => 'h.host_locked',
        ]);
        $sqlTranslator->addNormalizer('is_activated', new BoolToEnumNormalizer());
        $sqlTranslator->addNormalizer('is_locked', new BoolToEnumNormalizer());
        $sqlTranslator->translateForConcatenator($concatenator);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));

        $sqlTranslator->bindSearchValues($statement);
        $concatenator->bindValuesToStatement($statement);
        $statement->execute();

        $sqlTranslator->calculateNumberOfRows($this->db);

        $hostTemplates = [];
        while (is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{
             *     host_id: int,
             *     host_name: string,
             *     host_alias: string,
             *     host_snmp_version: string|null,
             *     host_snmp_community: string|null,
             *     host_location: int|null,
             *     command_command_id: int|null,
             *     command_command_id_arg1: string|null,
             *     timeperiod_tp_id: int|null,
             *     host_max_check_attemps: int|null,
             *     host_check_interval: int|null,
             *     host_retry_check_interval: int|null,
             *     host_active_checks_enabled: string|null,
             *     host_passive_checks_enabled: string|null,
             *     host_notifications_enabled: string|null,
             *     host_notification_options: string|null,
             *     host_notification_interval: int|null,
             *     timeperiod_tp_id2: int|null,
             *     cg_additive_inheritance: int|null,
             *     contact_additive_inheritance: int|null,
             *     host_first_notification_delay: int|null,
             *     host_recovery_notification_delay: int|null,
             *     host_acknowledgement_timeout: int|null,
             *     host_check_freshness: string|null,
             *     host_freshness_threshold: int|null,
             *     host_flap_detection_enabled: string|null,
             *     host_low_flap_threshold: int|null,
             *     host_high_flap_threshold: int|null,
             *     host_event_handler_enabled: string|null,
             *     command_command_id2: int|null,
             *     command_command_id_arg2: string|null,
             *     host_comment: string|null,
             *     host_activate: string|null,
             *     host_locked: int|null,
             *     ehi_notes_url: string|null,
             *     ehi_notes: string|null,
             *     ehi_action_url: string|null,
             *     ehi_icon_image: int|null,
             *     ehi_icon_image_alt: string|null,
             *     severity_id: int|null
             * } $result */
            $hostTemplates[] = $this->createHostTemplateFromArray($result);
        }

        return $hostTemplates;
    }

    /**
     * @param array{
     *     host_id: int,
     *     host_name: string,
     *     host_alias: string,
     *     host_snmp_version: string|null,
     *     host_snmp_community: string|null,
     *     host_location: int|null,
     *     command_command_id: int|null,
     *     command_command_id_arg1: string|null,
     *     timeperiod_tp_id: int|null,
     *     host_max_check_attemps: int|null,
     *     host_check_interval: int|null,
     *     host_retry_check_interval: int|null,
     *     host_active_checks_enabled: string|null,
     *     host_passive_checks_enabled: string|null,
     *     host_notifications_enabled: string|null,
     *     host_notification_options: string|null,
     *     host_notification_interval: int|null,
     *     timeperiod_tp_id2: int|null,
     *     cg_additive_inheritance: int|null,
     *     contact_additive_inheritance: int|null,
     *     host_first_notification_delay: int|null,
     *     host_recovery_notification_delay: int|null,
     *     host_acknowledgement_timeout: int|null,
     *     host_check_freshness: string|null,
     *     host_freshness_threshold: int|null,
     *     host_flap_detection_enabled: string|null,
     *     host_low_flap_threshold: int|null,
     *     host_high_flap_threshold: int|null,
     *     host_event_handler_enabled: string|null,
     *     command_command_id2: int|null,
     *     command_command_id_arg2: string|null,
     *     host_comment: string|null,
     *     host_activate: string|null,
     *     host_locked: int|null,
     *     ehi_notes_url: string|null,
     *     ehi_notes: string|null,
     *     ehi_action_url: string|null,
     *     ehi_icon_image: int|null,
     *     ehi_icon_image_alt: string|null,
     *     severity_id: int|null
     * } $result
     *
     * @return HostTemplate
     */
    private function createHostTemplateFromArray(array $result): HostTemplate
    {
        /* Note:
         * Due to legacy installations
         * some properties (host_snmp_version, host_location)
         * might sometimes still be set at 0|'0' instead of NULL in DB
         */

        return new HostTemplate(
            $result['host_id'],
            $result['host_name'],
            $result['host_alias'],
            match ($result['host_snmp_version']) {
                null, '', '0' => null,
                default => SnmpVersion::from($result['host_snmp_version']),
            },
            (string) $result['host_snmp_community'],
            0 === $result['host_location'] ? null : $result['host_location'],
            $result['severity_id'],
            $result['command_command_id'],
            (string) $result['command_command_id_arg1'],
            $result['timeperiod_tp_id'],
            $result['host_max_check_attemps'],
            $result['host_check_interval'],
            $result['host_retry_check_interval'],
            null !== $result['host_active_checks_enabled']
                ? YesNoDefault::from($result['host_active_checks_enabled'])
                : YesNoDefault::Default,
            null !== $result['host_passive_checks_enabled']
                ? YesNoDefault::from($result['host_passive_checks_enabled'])
                : YesNoDefault::Default,
            null !== $result['host_notifications_enabled']
                ? YesNoDefault::from($result['host_notifications_enabled'])
                : YesNoDefault::Default,
            match ($result['host_notification_options']) {
                null, '' => [],
                default => HostEvent::fromLegacyString($result['host_notification_options']),
            },
            $result['host_notification_interval'],
            $result['timeperiod_tp_id2'],
            (bool) $result['cg_additive_inheritance'],
            (bool) $result['contact_additive_inheritance'],
            $result['host_first_notification_delay'],
            $result['host_recovery_notification_delay'],
            $result['host_acknowledgement_timeout'],
            null !== $result['host_check_freshness']
                ? YesNoDefault::from($result['host_check_freshness'])
                : YesNoDefault::Default,
            $result['host_freshness_threshold'],
            null !== $result['host_flap_detection_enabled']
                ? YesNoDefault::from($result['host_flap_detection_enabled'])
                : YesNoDefault::Default,
            $result['host_low_flap_threshold'],
            $result['host_high_flap_threshold'],
            null !== $result['host_event_handler_enabled']
                ? YesNoDefault::from($result['host_event_handler_enabled'])
                : YesNoDefault::Default,
            $result['command_command_id2'],
            (string) $result['command_command_id_arg2'],
            (string) $result['ehi_notes_url'],
            (string) $result['ehi_notes'],
            (string) $result['ehi_action_url'],
            $result['ehi_icon_image'],
            (string) $result['ehi_icon_image_alt'],
            (string) $result['host_comment'],
            (bool) $result['host_activate'],
            (bool) $result['host_locked']
        );
    }
}
