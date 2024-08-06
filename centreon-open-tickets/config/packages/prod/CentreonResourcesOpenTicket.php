<?php

/*
 * Centreon
 *
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CentreonOpenTickets\Resources\Infrastructure\Repository\OpenTicketExtraDataProvider;
use CentreonOpenTickets\Resources\Infrastructure\API\TicketExtraDataFormatter;

return static function (ContainerConfigurator $container): void {
    $openTicketsRouteConfigFileRelativePath = __DIR__ . '/../../../www/modules/centreon-open-tickets/routes/CentreonOpenTickets.yaml';

    /**
     * Only assign tags to the services if Open Ticket Module is installed through packages (files)
     * and web (inserted in database).
     */
    if (
        file_exists($openTicketsRouteConfigFileRelativePath) === true
        && filesize($openTicketsRouteConfigFileRelativePath) !== 0
    ) {
        $services = $container->services();

        $services->get(OpenTicketExtraDataProvider::class)
            ->tag('monitoring.resource.extra.providers');

        $services->get(TicketExtraDataFormatter::class)
            ->tag('monitoring.resource.extra.presenter.providers');
    }
};

