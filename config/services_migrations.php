<?php

declare(strict_types=1);

use AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Migrations\Version20261002140000;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/**
 * Loaded only when DoctrineMigrationsBundle is enabled. Each migration is a
 * service named after its class; a new one is added to this list, never
 * picked up by scanning a directory.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->tag('doctrine_migrations.migration');

    foreach ([Version20261002140000::class] as $migration) {
        $services->set($migration)->arg('$table', param('outbox.table'));
    }
};
