<?php

/**
 * Class
 *
 * @class ImportExportContext
 */
class ImportExportContext extends CentreonAwieContext
{
    /**
     * @When I export an object
     */
    public function iExportAnObject(): void
    {
        $this->iAmOnTheExportPage();
        $this->assertFind('css', '#contact')->click();
        $this->assertFind('css', '.bt_success')->click();
    }

    /**
     * @Then I have a file
     */
    public function iHaveAFile(): void
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
            if (str_ends_with("$file", 'zip')) {
                $fileCreate = true;
            }
        }

        if (!$fileCreate) {
            throw new \Exception('File not create');
        }
    }
}
