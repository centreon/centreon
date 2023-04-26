<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\VendorConfigurationPage;
use Centreon\Test\Behat\Configuration\VendorConfigurationListingPage;

class VendorConfigurationContext extends CentreonContext
{
    protected $currentPage;

    protected $initialProperties = array(
        'name' => 'vendorName',
        'alias' => 'vendorAlias',
        'description' => 'vendorDescription'
    );

    protected $updatedProperties = array(
        'name' => 'vendorNameChanged',
        'alias' => 'vendorAliasChanged',
        'description' => 'vendorDescriptionChanged'
    );

    /**
     * @Given a vendor is configured
     */
    public function aVendorIsConfigured()
    {
        $this->currentPage = new VendorConfigurationPage($this);
        $this->currentPage->setProperties($this->initialProperties);
        $this->currentPage->save();
    }

    /**
     * @When I change the properties of a vendor
     */
    public function iChangeThePropertiesOfAVendor()
    {
        $this->currentPage = new VendorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name']);
        $this->currentPage->setProperties($this->updatedProperties);
        $this->currentPage->save();
    }

    /**
     * @Then the properties are updated
     */
    public function thePropertiesAreUpdated()
    {
        $this->currentPage = new VendorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->updatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->updatedProperties);
    }

    /**
     * @When I duplicate a vendor
     */
    public function iDuplicateAVendor()
    {
        $this->currentPage = new VendorConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Duplicate');
    }

    /**
     * @Then the new vendor has the same properties
     */
    public function theNewVendorHasTheSameProperties()
    {
        $this->currentPage = new VendorConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name'] . '_1');
        $newProperties = $this->initialProperties;
        $newProperties['name'] = $this->initialProperties['name'] . '_1';
        $this->comparePageProperties($this->currentPage, $newProperties);
    }

    /**
     * @When I delete a vendor
     */
    public function iDeleteAVendor()
    {
        $this->currentPage = new VendorConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Delete');
    }

    /**
     * @Then the deleted object is not displayed in the list
     */
    public function theDeletedObjectIsNotDisplayedInTheList()
    {
        $this->spin(
            function ($context) {
                $this->currentPage = new VendorConfigurationListingPage($this);
                $object = $this->currentPage->getEntries();
                $bool = true;
                foreach ($object as $value) {
                    $bool = $bool && $value['name'] != $this->initialProperties['name'];
                }
                return $bool;
            },
            "The service is not being deleted.",
            5
        );
    }
}
