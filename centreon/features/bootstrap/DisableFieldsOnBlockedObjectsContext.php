<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationListingPage;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationPage;

class DisableFieldsOnBlockedObjectsContext extends CentreonContext
{
    /**
     * @Given a blocked object template
     */
    public function aBlockedObjectTemplate()
    {
        $newHostTemplate = new HostTemplateConfigurationPage($this);
        $newHostTemplate->setProperties(array(
            'name' => 'myHostTemplate',
            'alias' => 'myAlias',
            'macros' => array('macro1' => '001')
        ));

        $newHostTemplate->save();
        $hostTemplate = new HostTemplateConfigurationListingPage($this, false);

        $centreonDb = $this->getCentreonDatabase();
        $centreonDb->query("UPDATE host SET host_locked = 1 WHERE host_name = 'myHostTemplate'");

        $hostTemplate = new HostTemplateConfigurationListingPage($this);
        $hostTemplate->setLockedElementsFilter(true);
        $hostTemplate = $hostTemplate->getEntries();
        $hostTemplate = $hostTemplate['myHostTemplate'];

        if (!$hostTemplate['locked']) {
            throw new \Exception('the host template myHostTemplate is not locked');
        };
    }

    /**
     * @When i open the form
     */
    public function iOpenTheForm()
    {
        $hostTemplate = new HostTemplateConfigurationListingPage($this);
        $hostTemplate->setLockedElementsFilter(true);
        $editHostTemplate = $hostTemplate->inspect('myHostTemplate');

        return $editHostTemplate;
    }

    /**
     * @Then the fields are frozen
     */
    public function theFieldsAreFrozen()
    {
        $this->iOpenTheForm();
        $macro = $this->getSession()->getPage()->find('css', '#macro li.clone_template span input[type="text"]');
        if (!$macro->getAttribute('disabled')) {
            throw new \Exception('the macros are not disabled');
        }
    }
}
