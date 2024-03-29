<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\ContactGroupsConfigurationPage;
use Centreon\Test\Behat\Configuration\ContactGroupConfigurationListingPage;
use Centreon\Test\Behat\Administration\ACLGroupConfigurationPage;

class ContactGroupConfigurationContext extends CentreonContext
{
    protected $currentPage;

    protected $initialProperties = array(
        'name' => 'contactGroupName',
        'alias' => 'contactGroupAlias',
        'contacts' => 'Guest',
        'acl' => 'ALL',
        'status' => 1,
        'comments' => 'contactGroupComment'
    );

    protected $updatedProperties = array(
        'name' => 'contactGroupNameChanged',
        'alias' => 'contactGroupAliasChanged',
        'contacts' => 'User',
        'acl' => 'aclGroupName',
        'status' => 1,
        'comments' => 'contactGroupCommentChanged'
    );

    protected $aclGroup = array(
        'group_name' => 'aclGroupName',
        'group_alias' => 'aclGroupAlias'
    );

    /**
     * @Given a contact group is configured
     */
    public function aContactGroupIsConfigured()
    {
        $this->currentPage = new ContactGroupsConfigurationPage($this);
        $this->currentPage->setProperties($this->initialProperties);
        $this->currentPage->save();
    }

    /**
     * @When I update the contact group properties
     */
    public function iConfigureTheContactGroupProperties()
    {
        $this->currentPage = new ACLGroupConfigurationPage($this);
        $this->currentPage->setProperties($this->aclGroup);
        $this->currentPage->save();
        $this->currentPage = new ContactGroupConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name']);
        $this->currentPage->setProperties($this->updatedProperties);
        $this->currentPage->save();
    }

    /**
     * @Then the contact group properties are updated
     */
    public function theContactGroupPropertiesAreUpdated()
    {
        $this->currentPage = new ContactGroupConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->updatedProperties['name']);
        $this->comparePageProperties($this->currentPage, $this->updatedProperties);
    }

    /**
     * @When I duplicate a contact group
     */
    public function iDuplicateAContactGroup()
    {
        $this->currentPage = new ContactGroupConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Duplicate');
    }

    /**
     * @Then the new contact group has the same properties
     */
    public function theNewContactGroupHasTheSameProperties()
    {
        $this->currentPage = new ContactGroupConfigurationListingPage($this);
        $this->currentPage = $this->currentPage->inspect($this->initialProperties['name'] . '_1');
        $newProperties = $this->initialProperties;
        $newProperties['name'] = $this->initialProperties['name'] . '_1';
        $this->comparePageProperties($this->currentPage, $newProperties);
    }

    /**
     * @When I delete a contact group
     */
    public function iDeleteAContactGroup()
    {
        $this->currentPage = new ContactGroupConfigurationListingPage($this);
        $object = $this->currentPage->getEntry($this->initialProperties['name']);
        $checkbox = $this->assertFind('css', 'input[type="checkbox"][name="select[' . $object['id'] . ']"]');
        $this->currentPage->checkCheckbox($checkbox);
        $this->setConfirmBox(true);
        $this->selectInList('select[name="o1"]', 'Delete');
    }

    /**
     * @Then the deleted contact group is not displayed in the list
     */
    public function theDeletedContactGroupIsNotDisplayedInTheList()
    {
        $this->spin(
            function ($context) {
                $this->currentPage = new ContactGroupConfigurationListingPage($this);
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
