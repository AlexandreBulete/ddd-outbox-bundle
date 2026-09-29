<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle;

use AlexandreBulete\DddOutboxBundle\Infrastructure\Doctrine\Type\JobIdType;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Asynchronous effects you can trust (ADR 0010): nothing lost, nothing
 * phantom, nothing twice — and every one of them followed, explained and
 * retried on its own.
 *
 * There is no outbox table: on the Messenger Doctrine transport, a message
 * sent while an action is handled is inserted in that action's transaction.
 * This bundle guards that property, follows each message, and gives the back
 * office a screen and a retry.
 *
 * @phpstan-type OutboxConfig array{
 *     table: non-empty-string,
 *     failure_transport: non-empty-string,
 *     retention_days: positive-int,
 *     admin: array{enabled: bool, grid_limits: list<int>},
 * }
 */
final class DddOutboxBundle extends AbstractBundle
{
    protected string $extensionAlias = 'outbox';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('table')
                    ->defaultValue('outbox_job')
                    ->cannotBeEmpty()
                    ->info('Table of the jobs follow-up.')
                ->end()
                ->scalarNode('failure_transport')
                    ->defaultValue('failed')
                    ->cannotBeEmpty()
                    ->info('The Messenger failure transport, where failed jobs wait to be retried.')
                ->end()
                ->integerNode('retention_days')
                    ->defaultValue(30)
                    ->min(1)
                    ->info('Succeeded jobs older than this are removed by `outbox:purge`. Failed ones stay.')
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Sylius back-office screen. Turn off for a headless deployment.')
                        ->end()
                        ->arrayNode('grid_limits')
                            ->integerPrototype()->end()
                            ->defaultValue([25, 50, 100])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Validated and defaulted by the tree in configure().
        /** @var OutboxConfig $config */
        $container->parameters()
            ->set('outbox.table', $config['table'])
            ->set('outbox.failure_transport', $config['failure_transport'])
            ->set('outbox.retention_days', $config['retention_days'])
            ->set('outbox.admin.grid_limits', $config['admin']['grid_limits']);

        $container->import($this->getPath() . '/config/services.php');

        if (self::migrationsEnabled($builder)) {
            $container->import($this->getPath() . '/config/services_migrations.php');
        }

        if ($config['admin']['enabled']) {
            $container->import($this->getPath() . '/config/services_admin.php');
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [JobIdType::NAME => JobIdType::class],
            ],
            'orm' => [
                'mappings' => [
                    'Outbox' => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => $this->getPath() . '/src/Infrastructure/Doctrine/Mapping',
                        'prefix' => 'AlexandreBulete\DddOutboxBundle\Domain\Model',
                        'alias' => 'Outbox',
                    ],
                ],
            ],
        ]);

        // The migration is a service (it needs `outbox.table`), which
        // DoctrineMigrationsBundle only looks up with this switch on.
        if (self::migrationsEnabled($builder)) {
            $builder->prependExtensionConfig('doctrine_migrations', ['enable_service_migrations' => true]);
        }

        $builder->prependExtensionConfig('framework', [
            'translator' => ['paths' => [$this->getPath() . '/translations']],
        ]);

        if (self::adminEnabled($builder)) {
            $builder->prependExtensionConfig('sylius_resource', [
                'mapping' => ['paths' => [$this->getPath() . '/src/Infrastructure/Sylius/Resource']],
            ]);
        }
    }

    /**
     * From `kernel.bundles`: inside loadExtension() the builder only knows
     * this extension, so hasExtension() would always answer false.
     */
    private static function migrationsEnabled(ContainerBuilder $builder): bool
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->getParameter('kernel.bundles');

        return in_array(DoctrineMigrationsBundle::class, $bundles, true);
    }

    /**
     * Read before the config tree is processed: raw values, own fallback.
     */
    private static function adminEnabled(ContainerBuilder $builder): bool
    {
        $enabled = true;
        foreach ($builder->getExtensionConfig('outbox') as $config) {
            $admin = $config['admin'] ?? null;
            if (is_array($admin) && is_bool($admin['enabled'] ?? null)) {
                $enabled = $admin['enabled'];
            }
        }

        return $enabled;
    }
}
