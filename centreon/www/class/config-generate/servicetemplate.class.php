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

require_once __DIR__ . '/abstract/service.class.php';

/**
 * Class
 *
 * @class ServiceTemplate
 */
class ServiceTemplate extends AbstractService
{
    /** @var null */
    protected $hosts = null;

    /** @var string */
    protected $generate_filename = 'serviceTemplates.cfg';

    /** @var string */
    protected string $object_name = 'service';

    /** @var array */
    public $service_cache = [];

    /** @var null */
    public $current_host_id = null;

    /** @var null */
    public $current_host_name = null;

    /** @var null */
    public $current_service_description = null;

    /** @var null */
    public $current_service_id = null;

    /** @var array */
    protected $loop_tpl = [];

    /** @var string */
    protected $attributes_select = '
        service_id,
        service_template_model_stm_id,
        command_command_id as check_command_id,
        command_command_id_arg as check_command_arg,
        timeperiod_tp_id as check_period_id,
        timeperiod_tp_id2 as notification_period_id,
        command_command_id2 as event_handler_id,
        command_command_id_arg2 as event_handler_arg,
        service_description as name,
        service_alias as service_description,
        display_name,
        service_is_volatile as is_volatile,
        service_max_check_attempts as max_check_attempts,
        service_normal_check_interval as check_interval,
        service_retry_check_interval as retry_interval,
        service_active_checks_enabled as active_checks_enabled,
        service_passive_checks_enabled as passive_checks_enabled,
        initial_state,
        service_obsess_over_service as obsess_over_service,
        service_check_freshness as check_freshness,
        service_freshness_threshold as freshness_threshold,
        service_event_handler_enabled as event_handler_enabled,
        service_low_flap_threshold as low_flap_threshold,
        service_high_flap_threshold as high_flap_threshold,
        service_flap_detection_enabled as flap_detection_enabled,
        service_process_perf_data as process_perf_data,
        service_retain_status_information as retain_status_information,
        service_retain_nonstatus_information as retain_nonstatus_information,
        service_notification_interval as notification_interval,
        service_notification_options as notification_options,
        service_notifications_enabled as notifications_enabled,
        contact_additive_inheritance,
        cg_additive_inheritance,
        service_first_notification_delay as first_notification_delay,
        service_recovery_notification_delay as recovery_notification_delay,
        service_stalking_options as stalking_options,
        service_register as register,
        service_use_only_contacts_from_host,
        esi_notes as notes,
        esi_notes_url as notes_url,
        esi_action_url as action_url,
        esi_icon_image as icon_image_id,
        esi_icon_image_alt as icon_image_alt,
        service_acknowledgement_timeout as acknowledgement_timeout
    ';

    /** @var string[] */
    protected $attributes_write = ['service_description', 'name', 'display_name', 'contacts', 'contact_groups', 'check_command', 'check_period', 'notification_period', 'event_handler', 'max_check_attempts', 'check_interval', 'retry_interval', 'initial_state', 'freshness_threshold', 'low_flap_threshold', 'high_flap_threshold', 'flap_detection_options', 'notification_interval', 'notification_options', 'first_notification_delay', 'recovery_notification_delay', 'stalking_options', 'register', 'notes', 'notes_url', 'action_url', 'icon_image', 'icon_id', 'icon_image_alt', 'acknowledgement_timeout'];

    /**
     * @param $serviceId
     *
     * @throws PDOException
     * @return void
     */
    private function getServiceGroups($serviceId): void
    {
        $host = Host::getInstance($this->dependencyInjector);
        $servicegroup = Servicegroup::getInstance($this->dependencyInjector);
        $this->service_cache[$serviceId]['sg'] = $servicegroup->getServiceGroupsForStpl($serviceId);
        $this->service_cache[$serviceId]['group_tags'] = [];
        foreach ($this->service_cache[$serviceId]['sg'] as &$sg) {
            if ($host->isHostTemplate($this->current_host_id, $sg['host_host_id'])) {
                $this->service_cache[$serviceId]['group_tags'][] = $sg['servicegroup_sg_id'];
                $servicegroup->addServiceInSg(
                    $sg['servicegroup_sg_id'],
                    $this->current_service_id,
                    $this->current_service_description,
                    $this->current_host_id,
                    $this->current_host_name
                );
            }
        }
    }

    /**
     * @param int $serviceId
     *
     * @throws PDOException
     */
    public function getServiceFromId(int $serviceId): void
    {
        if (is_null($this->stmt_service)) {
            $this->stmt_service = $this->backend_instance->db->prepare(
                'SELECT ' . $this->attributes_select . ' '
                . 'FROM service '
                . 'LEFT JOIN extended_service_information '
                . 'ON extended_service_information.service_service_id = service.service_id '
                . "WHERE service_id = :service_id AND service_activate = '1' "
            );
        }
        $this->stmt_service->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $this->stmt_service->execute();
        $results = $this->stmt_service->fetchAll(PDO::FETCH_ASSOC);
        $this->service_cache[$serviceId] = array_pop($results);
    }

    /**
     * @param $service_id
     *
     * @throws PDOException
     * @return int|void
     */
    private function getSeverity($service_id)
    {
        if (isset($this->service_cache[$service_id]['severity_id'])) {
            return 0;
        }

        $this->service_cache[$service_id]['severity_id']
            = Severity::getInstance($this->dependencyInjector)->getServiceSeverityByServiceId($service_id);
        $severity = Severity::getInstance($this->dependencyInjector)
            ->getServiceSeverityById($this->service_cache[$service_id]['severity_id']);
        if (! is_null($severity)) {
            $macros = [
                '_CRITICALITY_LEVEL' => $severity['level'],
                '_CRITICALITY_ID' => $severity['sc_id'],
                'severity' =>  $severity['sc_id'],
            ];

            $this->service_cache[$service_id]['macros'] = array_merge(
                $this->service_cache[$service_id]['macros'] ?? [],
                $macros
            );
        }
    }

    /**
     * @param $service_id
     *
     * @throws PDOException
     * @return mixed|null
     */
    public function generateFromServiceId($service_id)
    {
        if (is_null($service_id)) {
            return null;
        }

        if (! isset($this->service_cache[$service_id])) {
            $this->getServiceFromId($service_id);
        }

        if (is_null($this->service_cache[$service_id])) {
            return null;
        }
        if ($this->checkGenerate($service_id)) {
            if (! isset($this->loop_tpl[$service_id])) {
                $this->loop_tpl[$service_id] = 1;
                // Need to go in only to check servicegroup <-> stpl link
                $this->getServiceTemplates($this->service_cache[$service_id]);
                $this->getServiceGroups($service_id);
            }

            return $this->service_cache[$service_id]['name'];
        }

        // avoid loop. we return nothing
        if (isset($this->loop_tpl[$service_id])) {
            return null;
        }
        $this->loop_tpl[$service_id] = 1;

        $this->getImages($this->service_cache[$service_id]);
        $this->getMacros($this->service_cache[$service_id]);
        $this->getServiceTemplates($this->service_cache[$service_id]);
        $this->getServiceCommands($this->service_cache[$service_id]);
        $this->getServicePeriods($this->service_cache[$service_id]);
        $this->getContactGroups($this->service_cache[$service_id]);
        $this->getContacts($this->service_cache[$service_id]);
        $this->getServiceGroups($service_id);

        // Set ServiceCategories
        $serviceCategory = ServiceCategory::getInstance($this->dependencyInjector);
        $this->insertServiceInServiceCategoryMembers($serviceCategory, $service_id);
        $this->service_cache[$service_id]['category_tags'] = $serviceCategory->getIdsByServiceId($service_id);

        $this->getSeverity($service_id);

        $this->generateObjectInFile($this->service_cache[$service_id], $service_id);

        return $this->service_cache[$service_id]['name'];
    }

    /**
     * @return void
     */
    public function resetLoop(): void
    {
        $this->loop_tpl = [];
    }

    /**
     * @throws Exception
     * @return void
     */
    public function reset(): void
    {
        $this->current_host_id = null;
        $this->current_host_name = null;
        $this->current_service_description = null;
        $this->current_service_id = null;
        $this->loop_stpl = [];
        $this->service_cache = [];
        parent::reset();
    }
}
