<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration;

use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddOutboxBundle\DddOutboxBundle;
use AlexandreBulete\DddSymfonyBundle\DddSymfonyBundle;
use AlexandreBulete\DddOutboxBundle\Tests\Integration\App\NotifyCustomer;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * The outbox as a project installs it, minus the back office: real Doctrine
 * transports on the database given by DDD_TEST_DATABASE_URL (a SQLite file
 * otherwise). Every table of the test carries the `outbox_test_` prefix, so a
 * shared database with other tables does not leak into the comparisons.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new DddSymfonyBundle();
        yield new DddOutboxBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ddd-outbox-bundle-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ddd-outbox-bundle-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $url = getenv('DDD_TEST_DATABASE_URL');

        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'messenger' => [
                'failure_transport' => 'failed',
                'transports' => [
                    'async' => [
                        'dsn' => 'doctrine://default?table_name=outbox_test_messages&queue_name=async',
                        // One retry, at once: enough to see `retrying` then `failed`.
                        'retry_strategy' => ['max_retries' => 1, 'delay' => 0],
                    ],
                    'failed' => 'doctrine://default?table_name=outbox_test_messages&queue_name=failed',
                ],
                'routing' => [NotifyCustomer::class => 'async'],
            ],
        ]);
        $container->extension('doctrine', [
            'dbal' => [
                'url' => is_string($url) && $url !== '' ? $url : 'sqlite:///' . sys_get_temp_dir() . '/ddd-outbox-bundle-tests/outbox.db',
                'schema_filter' => '~^outbox_test_~',
            ],
            'orm' => [
                'mappings' => [
                    'TestApp' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/App',
                        'prefix' => 'AlexandreBulete\DddOutboxBundle\Tests\Integration\App',
                    ],
                ],
            ],
        ]);
        $container->extension('outbox', [
            'table' => 'outbox_test_job',
            'admin' => ['enabled' => false],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->load('AlexandreBulete\DddOutboxBundle\Tests\Integration\App\\', __DIR__ . '/App/*Handler.php');

        $services->alias('test.command_bus', CommandBusInterface::class)->public();

        // Failures are provoked on purpose: what they log is expected.
        $services->set('logger', NullLogger::class);
    }
}
