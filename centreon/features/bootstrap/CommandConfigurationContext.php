<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\CommandConfigurationPage;
use Centreon\Test\Behat\Configuration\CommandConfigurationListingPage;

class CommandConfigurationContext extends CentreonContext
{
    protected $currentPage;

    protected $initialProperties = array(
        'command_name' => 'aCommandName',
        'command_type' => 3,
        'command_line' => 'commandLine',
        'enabled_shell' => 1,
        'argument_example' => 'commandArgumentExample',
        'connectors' => 'Perl Connector',
        'graph_template' => 'Storage',
        'enabled' => 1,
        'comment' => 'commandComment'
    );

    protected $updatedProperties = array(
        'command_name' => 'aCommandNameChanged',
        'command_type' => 4,
        'command_line' => 'commandLineChanged',
        'enabled_shell' => 0,
        'argument_example' => 'commandArgumentExampleChanged',
        'connectors' => 'SSH Connector',
        'graph_template' => 'Memory',
        'enabled' => 1,
        'comment' => 'commandCommentChanged'
    );

    /**
     * @Given a command is configured
     */
    public function aCommandIsConfigured()
    {
        $this->currentPage = new CommandConfigurationPage($this);
        $this->currentPage->setProperties($this->initialProperties);
        $this->currentPage->save();
    }

    /**
     * @When I change the properties of a command
     */
    public function iChangeThePropertiesOfACommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
        $this->currentPage->setProperties($this->updatedProperties);
        $this->currentPage->save();
    }

    /**
     * @Then the properties are updated
     */
    public function thePropertiesAreUpdated()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 4);
        $this->currentPage = $this->currentPage->inspect($this->updatedProperties['command_name']);
        $this->comparePageProperties($this->currentPage, $this->updatedProperties);
    }

    /**
     * @When I duplicate a command
     */
    public function iDuplicateACommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $object = $this->currentPage->getEntry($this->initialProperties['command_name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $duplicateButton = $this->assertFind('css', '#duplicate_cmd');
        $duplicateButton->click();
    }

    /**
     * @Then the new command has the same properties
     */
    public function theNewCommandHasTheSameProperties()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name'] . '_1');
        $newProperties = $this->initialProperties;
        $newProperties['command_name'] = $this->initialProperties['command_name'] . '_1';
        $this->comparePageProperties($this->currentPage, $newProperties);
    }

    /**
     * @When I delete a command
     */
    public function iDeleteACommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $object = $this->currentPage->getEntry($this->initialProperties['command_name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $deleteButton = $this->assertFind('css', '#delete_cmd');
        $deleteButton->click();
    }

    /**
     * @Then the deleted command is not displayed in the list
     */
    public function theDeletedCommandIsNotDisplayedInTheList()
    {
        $this->spin(
            function ($context) {
                $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
                $object = $this->currentPage->getEntries();
                $bool = true;
                foreach ($object as $value) {
                    $bool = $bool && $value['name'] != $this->initialProperties['command_name'];
                }
                return $bool;
            },
            "The command is not being deleted.",
            5
        );
    }

    /**
     * @When I create a check command
     */
    public function iCreateACheckCommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
        $this->currentPage->setProperties(array(
            'command_type' => 2
        ));
        $this->currentPage->save();
    }

    /**
     * @Then the command is displayed on the checks page
     */
    public function theCommandIsDisplayedOnTheChecksPage()
    {
        /*$this->spin(
            function ($context) {*/
                $this->currentPage = new CommandConfigurationListingPage($this, true, 2);
                $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
            /*},
            "The command is not on the good page.",
            5
        );*/
    }

    /**
     * @When I create a notification command
     */
    public function iCreateANotificationCommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
        $this->currentPage->setProperties(array(
            'command_type' => 1
        ));
        $this->currentPage->save();
    }

    /**
     * @Then the command is displayed on the notifications page
     */
    public function theCommandIsDisplayedOnTheNotificationsPage()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 1);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
    }

    /**
     * @When I create a discovery command
     */
    public function iCreateADiscoveryCommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
        $this->currentPage->setProperties(array(
            'command_type' => 3
        ));
        $this->currentPage->save();
    }

    /**
     * @Then the command is displayed on the discovery page
     */
    public function theCommandIsDisplayedOnTheDiscoveryPage()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
    }

    /**
     * @When I create a miscellaneous command
     */
    public function iCreateAMiscellaneousCommand()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 3);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
        $this->currentPage->setProperties(array(
            'command_type' => 4
        ));
        $this->currentPage->save();
    }

    /**
     * @Then the command is displayed on the miscellaneous page
     */
    public function theCommandIsDisplayedOnTheMiscellaneousPage()
    {
        $this->currentPage = new CommandConfigurationListingPage($this, true, 4);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['command_name']);
    }
}
