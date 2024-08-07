<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\HostCategoryConfigurationPage;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationListingPage;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationPage;

class HostTemplateBasicsOperationsContext extends CentreonContext
{
    public const PASSWORD_REPLACEMENT_VALUE = '**********';
    protected $currentPage;

    protected $hostCategory1 = array(
        'name' => 'hostCategory1Name',
        'alias' => 'hostCategory1Alias',
        'severity' => 1,
        'severity_level' => 2,
        'severity_icon' => 'centreon (png)'
    );

    protected $hostCategory2 = array(
        'name' => 'hostCategory2Name',
        'alias' => 'hostCategory2Alias',
        'severity' => 1,
        'severity_level' => 13,
        'severity_icon' => 'centreon (png)'
    );

    protected $hostCategory3 = array(
        'name' => 'hostCategory3Name',
        'alias' => 'hostCategory3Alias'
    );

    protected $hostCategory4 = array(
        'name' => 'hostCategory4Name',
        'alias' => 'hostCategory4Alias'
    );

    protected $initialProperties = array(
        'name' => 'hostTemplateName',
        'alias' => 'hostTemplateAlias',
        'snmp_community' => 'snmp',
        'snmp_version' => '2c',
        'location' => 'Europe/Paris',
        'templates' => array(
            'generic-host'
        ),
        'check_command' => 'check_http',
        'command_arguments' => '!hostTemplateCommandArgument',
        'macros' => array(
            'HOSTTEMPLATEMACRONAME' => '22'
        ),
        'check_period' => 'workhours',
        'max_check_attempts' => 34,
        'normal_check_interval' => 5,
        'retry_check_interval' => 10,
        'active_checks_enabled' => 2,
        'passive_checks_enabled' => 0,
        'notifications_enabled' => 1,
        'contacts' => 'Guest',
        'contact_groups' => 'Supervisors',
        'notify_on_down' => 1,
        'notify_on_unreachable' => 1,
        'notify_on_recovery' => 1,
        'notify_on_flapping' => 1,
        'notify_on_downtime_scheduled' => 1,
        'notify_on_none' => 0,
        'notification_interval' => 17,
        'notification_period' => 'none',
        'first_notification_delay' => 4,
        'recovery_notification_delay' => 3,
        'service_templates' => 'generic-service',
        'parent_host_categories' => 'hostCategory3Name',
        'acknowledgement_timeout' => 2,
        'check_freshness' => 0,
        'freshness_threshold' => 34,
        'flap_detection_enabled' => 1,
        'low_flap_threshold' => 67,
        'high_flap_threshold' => 85,
        'event_handler_enabled' => 2,
        'event_handler' => 'check_https',
        'event_handler_arguments' => '!event_handler_arguments',
        'url' => 'hostTemplateChangeUrl',
        'notes' => 'hostTemplateChangeNotes',
        'action_url' => 'hostTemplateChangeActionUrl',
        'icon' => 'centreon (png)',
        'alt_icon' => 'hostTemplateChangeIcon',
        'severity_level' => 'hostCategory1Name (2)',
        'comments' => 'hostTemplateChangeComments'
    );

    protected $duplicatedProperties = array(
        'name' => 'hostTemplateName_1',
        'alias' => 'hostTemplateAlias',
        'snmp_community' => self::PASSWORD_REPLACEMENT_VALUE,
        'snmp_version' => '2c',
        'location' => 'Europe/Paris',
        'templates' => array(
            'generic-host'
        ),
        'check_command' => 'check_http',
        'command_arguments' => '!hostTemplateCommandArgument',
        'macros' => array(
            'HOSTTEMPLATEMACRONAME' => '22'
        ),
        'check_period' => 'workhours',
        'max_check_attempts' => 34,
        'normal_check_interval' => 5,
        'retry_check_interval' => 10,
        'active_checks_enabled' => 2,
        'passive_checks_enabled' => 0,
        'notifications_enabled' => 1,
        'contacts' => 'Guest',
        'contact_groups' => 'Supervisors',
        'notify_on_down' => 1,
        'notify_on_unreachable' => 1,
        'notify_on_recovery' => 1,
        'notify_on_flapping' => 1,
        'notify_on_downtime_scheduled' => 1,
        'notify_on_none' => 0,
        'notification_interval' => 17,
        'notification_period' => 'none',
        'first_notification_delay' => 4,
        'recovery_notification_delay' => 3,
        'service_templates' => 'generic-service',
        'parent_host_categories' => 'hostCategory3Name',
        'acknowledgement_timeout' => 2,
        'check_freshness' => 0,
        'freshness_threshold' => 34,
        'flap_detection_enabled' => 1,
        'low_flap_threshold' => 67,
        'high_flap_threshold' => 85,
        'event_handler_enabled' => 2,
        'event_handler' => 'check_https',
        'event_handler_arguments' => '!event_handler_arguments',
        'url' => 'hostTemplateChangeUrl',
        'notes' => 'hostTemplateChangeNotes',
        'action_url' => 'hostTemplateChangeActionUrl',
        'icon' => 'centreon (png)',
        'alt_icon' => 'hostTemplateChangeIcon',
        'severity_level' => 'hostCategory1Name (2)',
        'comments' => 'hostTemplateChangeComments'
    );

    protected $update = array(
        'name' => 'hostTemplateNameChanged',
        'alias' => 'hostTemplateAliasChanged',
        'snmp_community' => 'snmpChanged',
        'snmp_version' => '3',
        'location' => 'Europe/Paris',
        'templates' => array(
            'Printers'
        ),
        'check_command' => 'check_https',
        'command_arguments' => '!hostTemplateCommandArgumentChanged',
        'macros' => array(
            'HOSTTEMPLATEMACRONAMECHANGED' => '11'
        ),
        'check_period' => 'nonworkhours',
        'max_check_attempts' => 34,
        'normal_check_interval' => 9,
        'retry_check_interval' => 4,
        'active_checks_enabled' => 1,
        'passive_checks_enabled' => 1,
        'notifications_enabled' => 0,
        'contacts' => 'User',
        'contact_groups' => 'Guest',
        'notify_on_down' => 0,
        'notify_on_unreachable' => 0,
        'notify_on_recovery' => 0,
        'notify_on_flapping' => 0,
        'notify_on_downtime_scheduled' => 0,
        'notify_on_none' => 0,
        'notification_interval' => 49,
        'notification_period' => 'workhours',
        'first_notification_delay' => 7,
        'recovery_notification_delay' => 8,
        'service_templates' => 'Ping-LAN',
        'parent_host_categories' => 'hostCategory4Name',
        'acknowledgement_timeout' => 0,
        'check_freshness' => 1,
        'freshness_threshold' => 15,
        'flap_detection_enabled' => 0,
        'low_flap_threshold' => 25,
        'high_flap_threshold' => 34,
        'event_handler_enabled' => 1,
        'event_handler' => 'check_http',
        'event_handler_arguments' => '!event_handler_argumentsChanged',
        'url' => 'hostTemplateChangeUrlChanged',
        'notes' => 'hostTemplateChangeNotesChanged',
        'action_url' => 'hostTemplateChangeActionUrlChanged',
        'icon' => '',
        'alt_icon' => 'hostTemplateChangeIconChanged',
        'severity_level' => 'hostCategory2Name (13)',
        'comments' => 'hostTemplateChangeCommentsChanged'
    );

    protected $updatedProperties = array(
        'name' => 'hostTemplateNameChanged',
        'alias' => 'hostTemplateAliasChanged',
        'snmp_community' => self::PASSWORD_REPLACEMENT_VALUE,
        'snmp_version' => '3',
        'location' => 'Europe/Paris',
        'templates' => array(
            'Printers'
        ),
        'check_command' => 'check_https',
        'command_arguments' => '!hostTemplateCommandArgumentChanged',
        'macros' => array(
            'HOSTTEMPLATEMACRONAME' => '22',
            'HOSTTEMPLATEMACRONAMECHANGED' => '11'
        ),
        'check_period' => 'nonworkhours',
        'max_check_attempts' => 34,
        'normal_check_interval' => 9,
        'retry_check_interval' => 4,
        'active_checks_enabled' => 1,
        'passive_checks_enabled' => 1,
        'notifications_enabled' => 0,
        'contacts' => 'User',
        'contact_groups' => 'Guest',
        'notify_on_down' => 0,
        'notify_on_unreachable' => 0,
        'notify_on_recovery' => 0,
        'notify_on_flapping' => 0,
        'notify_on_downtime_scheduled' => 0,
        'notify_on_none' => 0,
        'notification_interval' => 49,
        'notification_period' => 'workhours',
        'first_notification_delay' => 7,
        'recovery_notification_delay' => 8,
        'service_templates' => 'Ping-LAN',
        'parent_host_categories' => 'hostCategory4Name',
        'acknowledgement_timeout' => 0,
        'check_freshness' => 1,
        'freshness_threshold' => 15,
        'flap_detection_enabled' => 0,
        'low_flap_threshold' => 25,
        'high_flap_threshold' => 34,
        'event_handler_enabled' => 1,
        'event_handler' => 'check_http',
        'event_handler_arguments' => '!event_handler_argumentsChanged',
        'url' => 'hostTemplateChangeUrlChanged',
        'notes' => 'hostTemplateChangeNotesChanged',
        'action_url' => 'hostTemplateChangeActionUrlChanged',
        'icon' => '',
        'alt_icon' => 'hostTemplateChangeIconChanged',
        'severity_level' => 'hostCategory2Name (13)',
        'comments' => 'hostTemplateChangeCommentsChanged'
    );

    /**
     * @Given a host template is configured
     */
    public function aHostTemplateIsConfigured()
    {
        $this->currentPage = new HostCategoryConfigurationPage($this);
        $this->currentPage->setProperties($this->hostCategory1);
        $this->currentPage->save();
        $this->currentPage = new HostCategoryConfigurationPage($this);
        $this->currentPage->setProperties($this->hostCategory2);
        $this->currentPage->save();
        $this->currentPage = new HostCategoryConfigurationPage($this);
        $this->currentPage->setProperties($this->hostCategory3);
        $this->currentPage->save();
        $this->currentPage = new HostCategoryConfigurationPage($this);
        $this->currentPage->setProperties($this->hostCategory4);
        $this->currentPage->save();
        $this->currentPage = new HostTemplateConfigurationPage($this);
        $this->currentPage->setProperties($this->initialProperties);
        $this->currentPage->save();
    }

    /**
     * @When I change the properties of a host template
     */
    public function iChangeThePropertiesOfAHostTemplate()
    {
        $this->currentPage = new HostTemplateConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name']);
        $this->currentPage->setProperties($this->update);
        $this->currentPage->save();
    }

    /**
     * @Then the properties are updated
     */
    public function thePropertiesAreUpdated()
    {
        $this->currentPage = new HostTemplateConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->updatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->updatedProperties);
    }

    /**
     * @When I duplicate a host template
     */
    public function iDuplicateAHostTemplate()
    {
        $this->currentPage = new HostTemplateConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Duplicate');
    }

    /**
     * @Then the new host template has the same properties
     */
    public function theNewHostTemplateHasTheSameProperties()
    {
        $this->currentPage = new HostTemplateConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->duplicatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->duplicatedProperties);
    }

    /**
     * @When I delete a host template
     */
    public function iDeleteAHostTemplate()
    {
        $this->currentPage = new HostTemplateConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Delete');
    }

    /**
     * @Then the deleted host is not displayed in the host list
     */
    public function theDeletedHostIsNotDisplayedInTheHostList()
    {
        $this->spin(
            function ($context) {
                $this->currentPage = new HostTemplateConfigurationListingPage($this);
                $object = $this->currentPage->getEntries();
                $bool = true;
                foreach ($object as $value) {
                    $bool = $bool && $value['name'] != $this->initialProperties['name'];
                }
                return $bool;
            },
            "The host category is not being deleted.",
            5
        );
    }
}
