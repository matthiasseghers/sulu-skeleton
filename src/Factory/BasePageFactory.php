<?php

declare(strict_types=1);

namespace App\Factory;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Exception\PageNotFoundException;
use Sulu\Page\Domain\Model\PageDimensionContentInterface;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGeneratorInterface;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorRequest;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

abstract class BasePageFactory
{
    use HandleTrait;

    public const string DEFAULT_LOCALE = 'en';
    public const string DEFAULT_WEBSPACE = 'website';

    public function __construct(
        MessageBusInterface $messageBus,
        protected readonly PageRepositoryInterface $pageRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ResourceLocatorGeneratorInterface $resourceLocatorGenerator,
    ) {
        $this->messageBus = $messageBus;
    }

    public static function slugify(string $text, string $divider = '-'): string
    {
        $text = (string) \preg_replace('~[^\pL\d]+~u', $divider, $text);
        $text = (string) \iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = (string) \preg_replace('~[^\-\w]+~', '', $text);
        $text = \trim($text, $divider);
        $text = (string) \preg_replace('~-+~', $divider, $text);
        $text = \strtolower($text);

        return '' === $text || '0' === $text ? 'n-a' : $text;
    }

    protected function findHomepage(string $locale = self::DEFAULT_LOCALE): ?PageInterface
    {
        return $this->pageRepository->findOneBy(
            filters: [
                'parentId' => null,
                'locale' => $locale,
            ],
            selects: [
                'with-page-content' => [''],
            ],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createPage(
        string $webspaceKey,
        array $data,
        ?string $parentUuid = null,
        bool $flush = true,
        bool $publish = true,
    ): PageInterface {
        if (!isset($data['url'])) {
            $data['url'] = $this->generateSlug($data['title'], (string) $data['locale'], $webspaceKey, $parentUuid);
        }
        if (!$parentUuid) {
            $parentUuid = self::findHomepage((string) $data['locale'])?->getUuid();
        }

        $envelope = $this->messageBus->dispatch(
            new Envelope(
                new CreatePageMessage($webspaceKey, $parentUuid, $data),
                $flush ? [new EnableFlushStamp()] : [],
            ),
        );

        /** @var PageInterface|null $page */
        $page = $envelope->last(HandledStamp::class)?->getResult();

        if (!$page instanceof PageInterface) {
            throw new RuntimeException(\sprintf('Failed to create page "%s"', $data['title'] ?? 'unknown'));
        }

        if ($publish) {
            $this->publishPage($page->getUuid(), (string) $data['locale'], $flush);
        }

        return $page;
    }

    protected function publishPage(string $uuid, string $locale, bool $flush = true): void
    {
        $this->messageBus->dispatch(
            new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    ['uuid' => $uuid],
                    $locale,
                    WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                $flush ? [new EnableFlushStamp()] : [],
            ),
        );
    }

    protected function findPage(
        string $uuid,
        string $locale = self::DEFAULT_LOCALE,
    ): ?PageInterface {
        try {
            return $this->pageRepository->getOneBy(
                [
                    'uuid' => $uuid,
                    'loadGhost' => false,
                    'locale' => $locale,
                    'stage' => DimensionContentInterface::STAGE_DRAFT,
                ],
                [
                    PageRepositoryInterface::GROUP_SELECT_PAGE_ADMIN => true,
                ],
            );
        } catch (PageNotFoundException $e) {
            $this->logger?->info(\sprintf('Page not found with UUID %s: %s', $uuid, $e->getMessage()));

            return null;
        }
    }

    private function generateSlug(
        string $title,
        string $locale = self::DEFAULT_LOCALE,
        string $webspaceKey = 'website',
        ?string $parentUuid = null,
    ): string {
        $request = new ResourceLocatorRequest(
            parts: ['title' => $title],
            locale: $locale,
            webspace: $webspaceKey,
            resourceKey: PageInterface::RESOURCE_KEY,
            resourceId: null,
            parentResourceId: $parentUuid,
            parentResourceKey: PageInterface::RESOURCE_KEY,
            routeSchema: '/{object["title"]}',
            relative: false,
        );

        return $this->resourceLocatorGenerator->generate($request);
    }
}
