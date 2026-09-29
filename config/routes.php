<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The retry action of the back office. Import only with `outbox.admin`
 * enabled:
 *
 *     outbox:
 *         resource: '@DddOutboxBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(dirname(__DIR__) . '/src/Infrastructure/Symfony/Controller/', 'attribute');
};
