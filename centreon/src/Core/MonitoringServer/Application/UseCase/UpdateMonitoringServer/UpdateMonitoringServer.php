<?php

declare(strict_types=1);

namespace Core\MonitoringServer\Application\UseCase\UpdateMonitoringServer;

use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;

final readonly class UpdateMonitoringServer
{
    public function __construct(
        private WriteMonitoringServerRepositoryInterface $writeRepository,
        private ReadMonitoringServerRepositoryInterface $readRepository
    ) {
    }

    public function __invoke(UpdateMonitoringServerRequest $request): void
    {
        $monitoringServer = $this->readRepository->get($request->id);

        $monitoringServer->update(
            name: $request->name,
            engineStartCommand: $request->engineStartCommand,
            engineStopCommand: $request->engineStopCommand,
            engineRestartCommand: $request->engineRestartCommand,
            engineReloadCommand: $request->engineReloadCommand,
            brokerReloadCommand: $request->brokerReloadCommand
        );

        $this->writeRepository->update($monitoringServer);
    }
}
