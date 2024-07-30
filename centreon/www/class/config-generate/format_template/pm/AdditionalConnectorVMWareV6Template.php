<?php

final class AdditionalConnectorVMWareV6Template
{
    /**
     * @param AdditionalConnector[] $additionalConnectors
     *
     * @return string
     */
    public function generate(array $additionalConnectors): string
    {
        return <<<EOF
            %centreon_vmware_config = (
                vsphere_server => $this->formatAdditionalConnectorConfiguration($additionalConnectors),
            );
            port => 
            1;
        EOF;
    }

    /**
     * @param AdditionalConnector $additionalConnectorConfigurations
     * @return string
     */
    private function formatAdditionalConnectorConfiguration(array $additionalConnectorConfigurations): string
    {
        $formattedAdditionalConnectorConfigurations = array_map(function (AdditionalConnector $additionalConnectorConfiguration) {
            return <<<EOF
                '$additionalConnectorConfiguration->getName()' => {
                    url => '$additionalConnectorConfiguration->getUrl()',
                    username => '$additionalConnectorConfiguration->getUsername()',
                    password => '$additionalConnectorConfiguration->getPassword()',
                }
            EOF;

        }, $additionalConnectorConfigurations);
    }
}