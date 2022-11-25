<?php

/*
** Copyright 2021 Centreon
**
** All rights reserved.
*/

use Centreon\Test\Behat\CentreonContext;

class CentreonAwieContext extends CentreonContext
{
    /**
     * @Given I am logged in a Centreon server with Awie installed
     */
    public function iAmLoggedInACentreonServerWithAWIEInstalled()
    {
        $this->launchCentreonWebContainer('awie');
        $this->iAmLoggedIn();
    }

    public function iAmOnTheExportPage()
    {
        $this->visit('main.php?p=61201');
        // Check that page is valid for this class.
        $mythis = $this;
        $this->spin(
            function ($context) use ($mythis) {
                return $context->getSession()->getPage()->has('css', '#poller');
            }
        );
    }

    public function iAmOnTheImportPage()
    {
        $this->visit('main.php?p=61202');
        $mythis = $this;
        $this->spin(
            function ($context) use ($mythis) {
                return $context->getSession()->getPage()->has('css', '#file');
            }
        );
    }
}
