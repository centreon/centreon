<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\ConnectorConfigurationPage;
use Centreon\Test\Behat\Configuration\ConnectorConfigurationListingPage;

class ConnectorConfigurationContext extends CentreonContext
{
    protected $currentPage;

    protected $initialProperties = array(
        'name' => 'connectorName',
        'description' => 'connectorDescription',
        'command_line' => 'connectorCommandLine',
        'command' => 'service-notify-by-email',
        'enabled' => 1
    );

    protected $duplicatedProperties = array(
        'name' => 'connectorName_1',
        'description' => 'connectorDescription',
        'command_line' => 'connectorCommandLine',
        'command' => '',
        'enabled' => 1
    );

    protected $updatedProperties = array(
        'name' => 'connectorNameChanged',
        'description' => 'connectorDescriptionChanged',
        'command_line' => 'connectorCommandLineChanged',
        'command' => 'service-notify-by-epager',
        'enabled' => 1
    );

    /**
     * @Given a connector is configured
     */
    public function aConnectorIsConfigured()
    {
        $this->currentPage = new ConnectorConfigurationPage($this);
        $this->currentPage->setProperties($this->initialProperties);
        $this->currentPage->save();
    }

    /**
     * @When I change the properties of a connector
     */
    public function iChangeThePropertiesOfAConnector()
    {
        $this->currentPage = new ConnectorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name']);
        $this->currentPage->setProperties($this->updatedProperties);
        $this->currentPage->save();
    }

    /**
     * @Then the properties are updated
     */
    public function thePropertiesAreUpdated()
    {
        $this->currentPage = new ConnectorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->updatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->updatedProperties);
    }

    /**
     * @When I duplicate a connector
     */
    public function iDuplicateAConnector()
    {
        $this->currentPage = new ConnectorConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Duplicate');
    }

    /**
     * @Then the new connector has the same properties
     */
    public function theNewConnectorHasTheSameProperties()
    {
        $this->currentPage = new ConnectorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->duplicatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->duplicatedProperties);
    }

    /**
     * @When I delete a connector
     */
    public function iDeleteAConnector()
    {
        $this->currentPage = new ConnectorConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Delete');
    }

    /**
     * @Then the deleted connector is not displayed in the list
     */
    public function theDeletedConnectorIsNotDisplayedInTheList()
    {
        $this->spin(
            function ($context) {
                $this->currentPage = new ConnectorConfigurationListingPage($this);
                $object = $this->currentPage->getEntries();
                $bool = true;
                foreach ($object as $value) {
                    $bool = $bool && $value['name'] != $this->initialProperties['name'];
                }
                return $bool;
            },
            "The connector is not being deleted.",
            5
        );
    }
}
