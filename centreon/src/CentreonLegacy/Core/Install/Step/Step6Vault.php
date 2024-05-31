<?php

namespace CentreonLegacy\Core\Install\Step;

class Step6Vault extends AbstractStep
{
    public function getContent()
    {
        $installDir = __DIR__ . '/../../../../../www/install';
        require_once $installDir . '/steps/functions.php';
        $template = getTemplate($installDir . '/steps/templates');

        $parameters = $this->getVaultConfiguration();

        $template->assign('title', _('Vault information'));
        $template->assign('step', 6.1);
        $template->assign('parameters', $parameters);

        return $template->fetch('content.tpl');
    }

    public function setVaultConfiguration(array $configuration)
    {
        $configurationFile = __DIR__ . '/../../../../../www/install/tmp/vault.json';
        file_put_contents($configurationFile, json_encode($configuration));
    }
}