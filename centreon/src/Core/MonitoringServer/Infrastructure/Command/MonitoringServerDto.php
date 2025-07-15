<?php

declare(strict_types=1);

namespace Core\MonitoringServer\Infrastructure\Command;

use Core\MonitoringServer\Model\MonitoringServer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MonitoringServerDto
{
    public function __construct(
        public int $id,
        public string $name,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineStartCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineStopCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineRestartCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $engineReloadCommand = null,
        #[Assert\Regex(
            pattern: MonitoringServer::VALID_COMMAND_REGEX,
            message: 'Invalid command format.'
        )]
        public ?string $brokerReloadCommand = null
    ) {
    }

    public static function fromModel(MonitoringServer $monitoringServer): self
    {
        return new self(
            id: $monitoringServer->getId(),
            name: $monitoringServer->getName(),
            engineStartCommand: $monitoringServer->getEngineStartCommand(),
            engineStopCommand: $monitoringServer->getEngineStopCommand(),
            engineRestartCommand: $monitoringServer->getEngineRestartCommand(),
            engineReloadCommand: $monitoringServer->getEngineReloadCommand(),
            brokerReloadCommand: $monitoringServer->getBrokerReloadCommand()
        );
    }
}