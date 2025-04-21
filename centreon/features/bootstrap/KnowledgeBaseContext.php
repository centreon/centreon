<?php

use Centreon\Test\Behat\CentreonContext;
use Centreon\Test\Behat\Configuration\HostConfigurationPage;
use Centreon\Test\Behat\Configuration\KBServiceListingPage;
use Centreon\Test\Behat\Configuration\ServiceConfigurationPage;
use Centreon\Test\Behat\Administration\KBParametersPage;

/**
 * Defines application features from the specific context.
 */
class KnowledgeBaseContext extends CentreonContext
{
    public function __construct()
    {
        parent::__construct();
        $this->hostName = 'MediawikiHost';
        $this->serviceHostName = 'Centreon-Server';
        $this->serviceName = 'MediawikiService';
    }

    /**
     * @Given I am logged in a Centreon server with MediaWiki installed
     */
    public function iAmLoggedInACentreonServerWithWikiInstalled()
    {
        $this->launchCentreonWebContainer('docker_compose_web', ['web', 'webdriver', 'mediawiki']);
        $this->container->waitForAvailableUrl(
            'http://' . $this->container->getHost() . ':' .
            $this->container->getPort(80, 'mediawiki') . '/index.php/Main_Page'
        );

        $this->iAmLoggedIn();

        $page = new KBParametersPage($this);
        $this->spin(
            function ($context) use ($page) {
                $page->setProperties(
                    array(
                        'kb_wiki_url' => 'http://' . $context->container->getContainerIpAddress('mediawiki'),
                        'kb_wiki_account' => 'WikiSysop',
                        'kb_wiki_password' => 'centreon'
                    )
                );
                $page->save();

                return true;
            },
            'Wiki page is not loaded.',
            120
        );
    }

    /**
     * @Given a host configured
     */
    public function aHostConfigured()
    {
        $hostPage = new HostConfigurationPage($this);
        $hostPage->setProperties(array(
            'name' => $this->hostName,
            'alias' => $this->hostName,
            'address' => 'localhost',
            'max_check_attempts' => 1,
            'normal_check_interval' => 1,
            'retry_check_interval' => 1,
            'active_checks_enabled' => 0,
            'passive_checks_enabled' => 1
        ));
        $hostPage->save();
        $this->restartAllPollers();
    }

    /**
     * @Given a service configured
     */
    public function aServiceConfigured()
    {
        $servicePage = new ServiceConfigurationPage($this);
        $servicePage->setProperties(array(
            'hosts' => $this->serviceHostName,
            'description' => $this->serviceName,
            'templates' => 'generic-service',
            'check_command' => 'check_centreon_dummy',
            'check_period' => '24x7',
            'max_check_attempts' => 1,
            'normal_check_interval' => 1,
            'retry_check_interval' => 1,
            'active_checks_enabled' => 0,
            'passive_checks_enabled' => 1
        ));
        $servicePage->save();
        $this->restartAllPollers();
    }

    /**
     * @Given the knowledge configuration page with procedure
     */
    public function theKnowledgeConfigurationPageWithProcedure()
    {
        $this->aHostConfigured();
        $this->iAddAProcedureConcerningThisHostInMediawiki();
        $this->aLinkTowardThisHostProcedureIsAvailableInConfiguration();
    }

    /**
     * @When I add a procedure concerning this host in MediaWiki
     */
    public function iAddAProcedureConcerningThisHostInMediawiki()
    {
        /* Go to the options page */
        $this->spin(
            function ($context) {
                $context->visit('/main.php?p=61001');

                return true;
            },

            'Host listing page was not loaded properly.',
            120
        );

        $this->spin(
            function ($context) {
                return $context->getSession()->getPage()->has('css', '.list_two td:nth-child(5) a:nth-child(1)');
            },
            'Wiki procedure link is not found.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '.list_two td:nth-child(5) a:nth-child(1)')->click();

                return true;
            },
            'Wiki procedure link is not clickable.',
            120
        );

        $this->spin(
            function ($context) {
                $windowNames = $context->getSession()->getWindowNames();

                return count($windowNames) > 1;
            },
            'Wiki procedure window is not opened.',
            120
        );

        $windows = $this->getSession()->getWindowNames();
        $this->getSession()->switchToWindow($windows[1]);

        /* Add wiki page */
        $this->spin(
            function ($context) {
                $checkurl = 'Host_:_' . $this->hostName;
                $currenturl = urldecode($context->getSession()->getCurrentUrl());
                if (!strstr($currenturl, $checkurl)) {
                    throw new Exception(
                        "Redirected to wrong page: $currenturl, should have contain $checkurl."
                    );
                }

                return true;
            },
            'Redirected to wrong host wiki page.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#wpTextbox1')->setValue('add wiki host page');
                $context->assertFind('css', 'input[name="wpSave"]')->click();

                return true;
            },
            'Wiki page is not loaded.',
            120
        );

        /* cron */
        $this->container->execute("php /usr/share/centreon/cron/centKnowledgeSynchronizer.php", $this->webService);

        /* Apply config */
        $this->restartAllPollers();
    }

    /**
     * @When I add a procedure concerning this service in MediaWiki
     */
    public function iAddAProcedureConcerningThisServiceInMediawiki()
    {
        $this->spin(
            function ($context) {
                $page = new KBServiceListingPage($context);
                // Create wiki page.
                $this->spin(
                    function ($context) use ($page) {
                        $page->createWikiPage(['host' => $context->serviceHostName, 'service' => $context->serviceName]);

                        return true;
                    },
                    'Service listing page was not loaded properly.',
                    120
                );
                return true;
            },
            'Service listing page was not initiated properly.',
            120
        );

        $this->spin(
            function ($context) {
                $windowNames = $context->getSession()->getWindowNames();
                return count($windowNames) > 1;
            },
            'Wiki procedure window is not opened.',
            120
        );
        $windowNames = $this->getSession()->getWindowNames();
        $this->getSession()->switchToWindow($windowNames[1]);

        // Check that wiki page is valid.
        $checkurl = 'Service_:_' . $this->serviceHostName . '_/_' . $this->serviceName;
        $this->spin(
            function ($context) use ($checkurl) {
                strstr(urldecode($context->getSession()->getCurrentUrl()), $checkurl);

                return true;
            },
            'Redirected to wrong service wiki page.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#wpTextbox1')->setValue('add wiki service page');
                $context->assertFind('css', 'input[name="wpSave"]')->click();

                return true;
            },
            'Wiki page is not loaded.',
            120
        );

        /* cron */
        $this->container->execute("php /usr/share/centreon/cron/centKnowledgeSynchronizer.php", $this->webService);

        /* Apply config */
        $this->restartAllPollers();
    }

    /**
     * @When I delete a wiki procedure
     */
    public function iDeleteAWikiProcedure()
    {
        /* Go to the page to options page */
        $this->spin(
            function ($context) {
                $context->visit('/main.php?p=61001');

                return true;
            },

            'Host listing page was not loaded properly.',
            120
        );

        $this->spin(
            function ($context) {
                $links = $context->getSession()->getPage()->findAll('css', 'a');
                foreach ($links as $link) {
                    if (str_contains(trim($link->getText()), 'Delete wiki page')) {
                        $link->click();
                        return true;
                    }
                }

                return false;
            },
            'delete link is not available',
            120
        );
    }

    /**
     * @Then a link towards this host procedure is available in configuration
     */
    public function aLinkTowardThisHostProcedureIsAvailableInConfiguration()
    {
        /* check url config */
        $this->spin(
            function ($context) {
                $context->visit('/main.php?p=61001');

                return true;
            },

            'Host listing page was not loaded properly.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '.list_two td:nth-child(2) a:nth-child(1)');

                return true;
            },
            'Host listing page was not loaded properly, link not found.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '.list_two td:nth-child(2) a:nth-child(1)')->click();

                return true;
            },
            'Host listing page was not loaded properly, link not clickable.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#c5 a:nth-child(1)');

                return true;
            },
            'Host listing page was not loaded properly, link 2 not found.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#c5 a:nth-child(1)')->click();

                return true;
            },
            'Host listing page was not loaded properly, link 2 not clickable.',
            120
        );

        $this->spin(
            function ($context) {
                $fieldValue = $context->assertFind('css', 'input[name="ehi_notes_url"]');
                $originalValue = $fieldValue->getValue();

                if (!strstr(
                    $originalValue,
                    './include/configuration/configKnowledge/proxy/proxy.php?host_name=$HOSTNAME$'
                )
                ) {
                    throw new Exception('Bad url');
                }

                return true;
            },
            'waiting for the link towards host conf timed out',
            120
        );
    }

    /**
     * @Then a link towards this service procedure is available in configuration
     */
    public function aLinkTowardThisServiceProcedureIsAvailableInConfiguration()
    {
        /* check url config */
        $this->spin(
            function ($context) {
                $context->visit('/main.php?p=60201');

                return true;
            },

            'Service listing page was not loaded properly.',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '.list_one:nth-child(8) td:nth-child(2) a');

                return true;
            },
            'waiting for the link towards service conf timed out, link 1 not found',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '.list_one:nth-child(8) td:nth-child(2) a')->click();

                return true;
            },
            'waiting for the link towards service conf timed out, link 1 not clickable',
            120
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#c5 a:nth-child(1)');
                return true;
            },
            'waiting for the link towards service conf timed out, link 2 not found',
            180
        );

        $this->spin(
            function ($context) {
                $context->assertFind('css', '#c5 a:nth-child(1)')->click();
                return true;
            },
            'waiting for the link towards service conf timed out, link 2 not clickable',
            180
        );

        $this->spin(
            function ($context) {
                $fieldValue = $context->assertFind('css', 'input[name="esi_notes_url"]');
                $originalValue = $fieldValue->getValue();

                if (!strstr(
                    $originalValue,
                    './include/configuration/configKnowledge/proxy/proxy.php?' .
                    'host_name=$HOSTNAME$&service_description=$SERVICEDESC$'
                )
                ) {
                    throw new Exception('Bad url');
                }

                return true;
            },
            'waiting for the link towards service conf timed out',
            120
        );
    }

    /**
     * @Then the page is deleted and the option disappear
     */
    public function thePageIsDeletedAndTheOptionDisappear()
    {
        $this->spin(
            function ($context) {
                if (str_contains($context->assertFind('css', '.list_two td:nth-child(4) font')->getHtml(), 'No wiki page defined')) {
                    return true;
                } else {
                    return false;
                }
            },
            'Delete option id display',
            120
        );
    }
}
