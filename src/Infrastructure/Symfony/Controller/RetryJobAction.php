<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Infrastructure\Symfony\Controller;

use AlexandreBulete\DddFoundation\Application\Command\CommandBusInterface;
use AlexandreBulete\DddOutboxBundle\Application\Command\RetryJob\RetryJobCommand;
use AlexandreBulete\DddOutboxBundle\Domain\ValueObject\JobId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * "Retry" on a failed job: dispatches RetryJobCommand — authorized and
 * journaled like any use case — and comes back to the list with the outcome.
 */
final class RetryJobAction
{
    public const CSRF_ID = 'outbox_retry_job';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly UrlGeneratorInterface $urls,
    ) {}

    #[Route(path: '/admin/jobs/{id}/retry', name: 'outbox_admin_job_retry', methods: ['POST'])]
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        if (!Ulid::isValid($id)) {
            throw new AccessDeniedHttpException('Invalid job id.');
        }

        try {
            $this->commandBus->dispatch(new RetryJobCommand(JobId::fromString($id)));
            $this->flash($request, 'success', 'outbox.job.retried');
        } catch (\DomainException $e) {
            // No longer failed, or no longer in the failure transport: said, not a 500.
            $this->flash($request, 'error', $e->getMessage());
        }

        return new RedirectResponse($this->urls->generate('outbox_admin_job_index'));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
