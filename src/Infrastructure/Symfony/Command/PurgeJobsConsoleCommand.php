<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Symfony\Command;

use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddOutboxBundle\Application\Command\PurgeJobs\PurgeJobsCommand;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'outbox:purge',
    description: 'Remove the follow-up of jobs that succeeded more than outbox.retention_days ago.',
)]
final class PurgeJobsConsoleCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClockInterface $clock,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $before = $this->clock->now()->modify(sprintf('-%d days', $this->retentionDays));

        $removed = $this->commandBus->dispatch(new PurgeJobsCommand($before));

        (new SymfonyStyle($input, $output))->success(sprintf(
            '%d succeeded job%s older than %s removed.',
            $removed,
            $removed === 1 ? '' : 's',
            $before->format('Y-m-d'),
        ));

        return Command::SUCCESS;
    }
}
