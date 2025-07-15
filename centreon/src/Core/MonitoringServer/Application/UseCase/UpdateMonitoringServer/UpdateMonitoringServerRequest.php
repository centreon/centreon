<?php


declare(strict_types=1);

namespace Core\MonitoringServer\Application\UseCase\UpdateMonitoringServer;

final readonly class UpdateMonitoringServerRequest
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $engineStartCommand,
        public ?string $engineStopCommand,
        public ?string $engineRestartCommand,
        public ?string $engineReloadCommand,
        public ?string $brokerReloadCommand,
    ) {
    }
}

