<?php

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration;

use Webmozart\Assert\Assert;

class TelegrafConfigurationParameters extends AbstractConfigurationParameters
{
    public const BROKER_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';

    /**
     * @throws AssertionException
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);

        Assert::range($this->parameters['conf_server_port'], 0, 65535, 'configuration.conf_server_port');

        $this->parameters['otel_public_certificate'] = $this->validateCertificatePath(
            $this->parameters['otel_public_certificate'],
            'configuration.otel_public_certificate'
        );
        $this->parameters['otel_private_key'] = $this->validateCertificatePath(
            $this->parameters['otel_private_key'],
            'configuration.otel_private_key'
        );
        $this->parameters['conf_certificate'] = $this->validateCertificatePath(
            $this->parameters['conf_certificate'],
            'configuration.conf_certificate'
        );
        $this->parameters['conf_private_key'] = $this->validateCertificatePath(
            $this->parameters['conf_private_key'],
            'configuration.conf_private_key'
        );
        $this->parameters['otel_ca_certificate'] = $this->validateCertificatePath(
            $this->parameters['otel_ca_certificate'],
            'configuration.otel_ca_certificate'
        );
    }

    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_DIRECTIVE;
    }
}
