<?php

declare(strict_types=1);

use AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Admin\Menu\OutboxMenuContributor;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Grid\JobGrid;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Grid\JobGridProvider;
use AlexandreBulete\DddOutboxBundle\Infrastructure\Symfony\Controller\RetryJobAction;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Loaded only when `outbox.admin.enabled` is true — everything Sylius.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(JobGridProvider::class);
    $services->set(JobGrid::class)->args([param('outbox.admin.grid_limits')]);
    $services->set(OutboxMenuContributor::class);

    $services->set(RetryJobAction::class)
        ->tag('controller.service_arguments');
};
