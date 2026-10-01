<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Sylius\Admin\Menu;

use AlexandreBulete\DddSyliusBundle\Admin\Menu\MenuContributorInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Authorization\PermissionCheckerInterface;
use AlexandreBulete\DddSymfonyBundle\Messenger\Tracing\ActorResolverInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registered only when `outbox.admin.enabled` is true. Shown to whoever may
 * list the jobs — to everyone when nothing checks permissions.
 *
 * Sits under a shared "System" section (get-or-create, so any system-oriented
 * contributor can create it once), next to the activity journal and the like.
 */
#[AutoconfigureTag('app.menu_contributor', ['priority' => 11])]
final readonly class OutboxMenuContributor implements MenuContributorInterface
{
    public function __construct(
        private ActorResolverInterface $actors,
        private ?PermissionCheckerInterface $checker = null,
    ) {}

    public function contribute(ItemInterface $menu): void
    {
        $actor = $this->actors->resolve();
        if ($this->checker !== null && ($actor === null || !$this->checker->isGranted($actor, 'outbox.find_jobs'))) {
            return;
        }

        $system = $menu->getChild('system') ?? $menu
            ->addChild('system')
            ->setLabel('outbox.menu.system')
            ->setLabelAttribute('icon', 'tabler:settings');

        $system
            ->addChild('outbox', ['route' => 'outbox_admin_job_index'])
            ->setLabel('outbox.menu.jobs')
            ->setLabelAttribute('icon', 'tabler:arrows-right-left');
    }
}
