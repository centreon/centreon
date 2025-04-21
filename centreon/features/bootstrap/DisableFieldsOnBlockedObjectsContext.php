<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationPage;
use Centreon\Test\Behat\Configuration\HostTemplateConfigurationListingPage;

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
            'address' => '127.0.0.1',
            'macros' => array('macro1' => '001')
        ));

        $newHostTemplate->save();
        $hostTemplate = new HostTemplateConfigurationListingPage($this, false);

        $centreonDb = $this->getCentreonDatabase();
        $centreonDb->query("UPDATE host SET host_locked = 1 WHERE host_name = 'myHostTemplate'");

        $hostTemplate = new HostTemplateConfigurationListingPage($this);
        $hostTemplate->setLockedElementsFilter(true);

        $this->spin(
            function ($context) use ($hostTemplate) {
                $entries = $hostTemplate->getEntries();
                
                if (!isset($entries['myHostTemplate']) && !$entries['myHostTemplate']['locked']) {
                    throw new \Exception('the host template myHostTemplate is not locked');
                };

                return true; 
            },
            'timeout : the host template myHostTemplate is not locked',
            120
        );
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

        $this->spin(
            function ($context) {
                $macro = $context->getSession()->getPage()->find('css', '#macro li.clone_template span input[type="text"]');
                if (!$macro->getAttribute('disabled')) {
                    throw new \Exception('the macros are not disabled');
                }

                return true;
            },
            'timeout : the macros are not disabled',
            120
        );
    }
}