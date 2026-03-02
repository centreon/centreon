<?php

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration;

use Webmozart\Assert\Assert;

class CmaConfigurationParameters extends AbstractConfigurationParameters
{
    public const BROKER_MODULE_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';
    public const DEFAULT_CHECK_INTERVAL = 60;
    public const DEFAULT_EXPORT_PERIOD = 60;

    public function __construct(array $parameters, private readonly bool $fromReadRepository = false)
    {
        parent::__construct($parameters);

        if ($this->parameters['agent_initiated'] === false) {
            $this->parameters['otel_public_certificate'] = null;
            $this->parameters['otel_private_key'] = null;
            $this->parameters['otel_ca_certificate'] = null;
            $this->parameters['tokens'] = [];
        } else {
            $this->parameters['otel_public_certificate'] = $this->validateCertificatePath(
                $this->parameters['otel_public_certificate'],
                'configuration.otel_public_certificate'
            );
            $this->parameters['otel_private_key'] = $this->validateCertificatePath(
                $this->parameters['otel_private_key'],
                'configuration.otel_private_key'
            );
            $this->parameters['otel_ca_certificate'] = $this->validateCertificatePath(
                $this->parameters['otel_ca_certificate'],
                'configuration.otel_ca_certificate'
            );

            if (! $this->fromReadRepository) {
                Assert::notEmpty($this->parameters['tokens'], '[configuration.tokens] Tokens cannot be empty.');
                foreach ($this->parameters['tokens'] as $token) {
                    Assert::stringNotEmpty($token['name'], '[configuration.tokens[].name] Token name cannot be empty.');
                }
            }
            if (! isset($this->parameters['port'])) {
                $this->parameters['port'] = AgentConfiguration::DEFAULT_PORT;
            }
            $port = $this->parameters['port'] ?? AgentConfiguration::DEFAULT_PORT;
            Assert::range($port, 0, 65535, '[configuration.port] Port must be between 0 and 65535. Got: %s');
        }

        if ($this->parameters['poller_initiated'] === false) {
            $this->parameters['hosts'] = [];
        } else {
            foreach ($this->parameters['hosts'] as $key => $host) {
                Assert::positiveInteger($host['id'], '[configuration.hosts[].id] Host id must be a positive integer. Got: %s');
                Assert::true(
                    filter_var($host['address'], FILTER_VALIDATE_IP) !== false
                    || filter_var($host['address'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
                    sprintf('[configuration.hosts[].address] "%s" is not a valid IP or domain.', $host['address'])
                );
                Assert::range($host['port'], 0, 65535, '[configuration.hosts[].port] Port must be between 0 and 65535. Got: %s');
                $this->parameters['hosts'][$key]['poller_ca_certificate'] = $this->validateCertificatePath(
                    $host['poller_ca_certificate'],
                    'configuration.hosts[].poller_ca_certificate'
                );

                if (! $this->fromReadRepository) {
                    Assert::notNull($host['token'], '[configuration.hosts[].token] Token cannot be null.');
                    Assert::stringNotEmpty(
                        $host['token']['name'] ?? '',
                        '[configuration.hosts[].token.name] Token name cannot be empty.'
                    );
                    Assert::positiveInteger(
                        $host['token']['creator_id'] ?? 0,
                        '[configuration.hosts[].token.creator_id] Creator id must be a positive integer. Got: %s'
                    );
                }
            }
        }
    }

    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_MODULE_DIRECTIVE;
    }
}
