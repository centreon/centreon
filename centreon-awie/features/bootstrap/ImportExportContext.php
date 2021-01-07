<?php

/**
 * Class ImportExportContext
 */
class ImportExportContext extends CentreonAwieContext
{
    /**
     * @When I export an object
     */
    public function iExportAnObject()
    {
        $this->iAmOnTheExportPage();
        $this->assertFind('css', '#contact')->click();
        $this->assertFind('css', '.bt_success')->click();
    }

    /**
     * @Then I have a file
     */
    public function iHaveAFile()
    {
        $mythis = $this;

        $this->spin(
            function ($context) use ($mythis) {
                if ($context->getSession()->getPage()->has('css', '.loadingWrapper')) {
                    return !$context->assertFind('css', '.loadingWrapper')->isVisible();
                } else {
                    return true;
                }
            }
        );

        $cmd = 'ls /tmp';
        $output = $this->container->execute(
            $cmd,
            'web'
        );
        $output = explode("\n", $output['output']);
        $fileCreate = false;
        foreach ($output as $file) {
            if (substr("$file", -3) == 'zip') {
                $fileCreate = true;
            }
        }

        if (!$fileCreate) {
            throw new \Exception('File not create');
        }
    }
}
