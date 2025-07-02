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

use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

require_once __DIR__ . '/object.class.php';

/**
 * Class
 *
 * @class AbstractService
 */
abstract class AbstractService extends AbstractObject
{
    /** @var array */
    protected $service_cache;

    // no flap_detection_options attribute
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
        service_description,
        service_alias as name,
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
        service_use_only_contacts_from_host,
        cg_additive_inheritance,
        service_first_notification_delay as first_notification_delay,
        service_recovery_notification_delay as recovery_notification_delay,
        service_stalking_options as stalking_options,
        service_register as register,
        esi_notes as notes,
        esi_notes_url as notes_url,
        esi_action_url as action_url,
        esi_icon_image as icon_image_id,
        esi_icon_image_alt as icon_image_alt,
        service_acknowledgement_timeout as acknowledgement_timeout
    ';

    /** @var string[] */
    protected $attributes_write = ['host_name', 'service_description', 'display_name', 'contacts', 'contact_groups', 'check_command', 'check_period', 'notification_period', 'event_handler', 'max_check_attempts', 'check_interval', 'retry_interval', 'initial_state', 'freshness_threshold', 'low_flap_threshold', 'high_flap_threshold', 'flap_detection_options', 'notification_interval', 'notification_options', 'first_notification_delay', 'recovery_notification_delay', 'stalking_options', 'register', 'notes', 'notes_url', 'action_url', 'icon_image', 'icon_id', 'icon_image_alt', 'acknowledgement_timeout'];

    /** @var string[] */
    protected $attributes_default = ['is_volatile', 'active_checks_enabled', 'passive_checks_enabled', 'event_handler_enabled', 'flap_detection_enabled', 'notifications_enabled', 'obsess_over_service', 'check_freshness', 'process_perf_data', 'retain_status_information', 'retain_nonstatus_information'];

    /** @var string[] */
    protected $attributes_array = [
        'use',
        'category_tags',
        'group_tags',
    ];

    /** @var string[] */
    protected $attributes_hash = ['macros'];

    /** @var array */
    protected $loop_stpl = []; // To be reset

    /** @var CentreonDBStatement|null */
    protected $stmt_macro = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_stpl = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_contact = null;

    /** @var CentreonDBStatement|null */
    protected $stmt_service = null;

    /**
     * @param $service
     *
     * @throws PDOException
     * @return void
     */
    protected function getImages(&$service)
    {
        $media = Media::getInstance($this->dependencyInjector);
        if (! isset($service['icon_image'])) {
            $service['icon_image'] = $media->getMediaPathFromId($service['icon_image_id']);
            $service['icon_id'] = $service['icon_image_id'];
        }
    }

    /**
     * @param $service
     *
     * @return int
     */
    protected function getMacros(&$service)
    {
        if (isset($service['macros'])) {
            return 1;
        }

        $service['macros'] = Macro::getInstance($this->dependencyInjector)
            ->getServiceMacroByServiceId($service['service_id']);

        return 0;
    }

    /**
     * @param $service
     *
     * @throws PDOException
     * @return void
     */
    protected function getServiceTemplates(&$service)
    {
        $service['use'] = [ServiceTemplate::getInstance($this->dependencyInjector)
            ->generateFromServiceId($service['service_template_model_stm_id'])];
    }

    /**
     * @param array $service (passing by Reference)
     *
     * @throws PDOException
     */
    protected function getContacts(array &$service): void
    {
        if (! isset($service['contacts_cache'])) {
            $contact = Contact::getInstance($this->dependencyInjector);
            $service['contacts_cache'] = $contact->getContactForService($service['service_id']);
        }
    }

    /**
     * @param array $service (passing by Reference)
     *
     * @throws PDOException
     */
    protected function getContactGroups(array &$service): void
    {
        if (! isset($service['contact_groups_cache'])) {
            $cg = Contactgroup::getInstance($this->dependencyInjector);
            $service['contact_groups_cache'] = $cg->getCgForService($service['service_id']);
        }
    }

    /**
     * @param $service_id
     * @param $command_label
     *
     * @return mixed|null
     */
    protected function findCommandName($service_id, $command_label)
    {
        $loop = [];

        $services_tpl = ServiceTemplate::getInstance($this->dependencyInjector)->service_cache;
        $service_id = $this->service_cache[$service_id]['service_template_model_stm_id'] ?? null;
        while (! is_null($service_id)) {
            if (isset($loop[$service_id])) {
                break;
            }
            $loop[$service_id] = 1;
            if (isset($services_tpl[$service_id][$command_label])
                && ! is_null($services_tpl[$service_id][$command_label])
            ) {
                return $services_tpl[$service_id][$command_label];
            }
            $service_id = $services_tpl[$service_id]['service_template_model_stm_id'] ?? null;
        }

        return null;
    }

    /**
     * @param $service
     * @param $result_name
     * @param $command_id_label
     * @param $command_arg_label
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return int
     */
    protected function getServiceCommand(&$service, $result_name, $command_id_label, $command_arg_label)
    {
        $command_name = Command::getInstance($this->dependencyInjector)
            ->generateFromCommandId($service[$command_id_label]);
        $command_arg = '';

        if (isset($service[$result_name])) {
            return 1;
        }
        $service[$result_name] = $command_name;
        if (isset($service[$command_arg_label])
            && ! is_null($service[$command_arg_label])
            && $service[$command_arg_label] != ''
        ) {
            $command_arg = $service[$command_arg_label];
            if (is_null($command_name)) {
                // Find Command Name in templates
                $command_name = $this->findCommandName($service['service_id'], $result_name);
                // Can have 'args after'. We replace
                if (! is_null($command_name)) {
                    $command_name = preg_replace('/!.*/', '', $command_name);
                    $service[$result_name] = $command_name . $command_arg;
                }
            } else {
                $service[$result_name] = $command_name . $command_arg;
            }
        }

        return 0;
    }

    /**
     * @param $service
     *
     * @throws LogicException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return void
     */
    protected function getServiceCommands(&$service)
    {
        $this->getServiceCommand($service, 'check_command', 'check_command_id', 'check_command_arg');
        $this->getServiceCommand($service, 'event_handler', 'event_handler_id', 'event_handler_arg');
    }

    /**
     * @param $service
     *
     * @throws PDOException
     * @return void
     */
    protected function getServicePeriods(&$service)
    {
        $period = Timeperiod::getInstance($this->dependencyInjector);
        // Optional "check_period_id" for Anomaly Detection for instance.
        $service['check_period'] = $period->generateFromTimeperiodId($service['check_period_id'] ?? null);
        // Mandatory "notification_period_id" field.
        $service['notification_period'] = $period->generateFromTimeperiodId($service['notification_period_id']);
    }

    /**
     * @param $service_id
     * @param $attr
     *
     * @return mixed|null
     */
    public function getString($service_id, $attr)
    {
        return $this->service_cache[$service_id][$attr] ?? null;
    }

    /**
     * @param ServiceCategory $serviceCategory
     * @param int $serviceId
     *
     * @throws PDOException
     */
    protected function insertServiceInServiceCategoryMembers(ServiceCategory $serviceCategory, int $serviceId): void
    {
        $this->service_cache[$serviceId]['serviceCategories']
            = $serviceCategory->getServiceCategoriesByServiceId($serviceId);

        foreach ($this->service_cache[$serviceId]['serviceCategories'] as $serviceCategoryId) {
            if (! is_null($serviceCategoryId)) {
                $serviceCategory->insertServiceToServiceCategoryMembers(
                    $serviceCategoryId,
                    $serviceId,
                    $this->service_cache[$serviceId]['service_description']
                );
            }
        }
    }
}
