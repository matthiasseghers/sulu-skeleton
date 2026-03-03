<?php

declare(strict_types=1);

namespace App\Factory\Pages;

use App\Factory\BasePageFactory;
use RuntimeException;
use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageInterface;

/**
 * Factory for creating and publishing pages in tests and fixtures.
 *
 * Usage:
 *   $pageFactory->createAndPublish(title: 'My Page', template: 'default', data: ['form' => 1]);
 */
class PageFactory extends BasePageFactory
{
    /**
     * Create and publish a new page under the homepage.
     *
     * @param array<string, mixed> $data       Extra page data (e.g. form ID, block data)
     * @param array<int, string>   $navigationContexts
     */
    public function createAndPublish(
        string $title,
        string $template = 'default',
        string $webspaceKey = self::DEFAULT_WEBSPACE,
        string $locale = self::DEFAULT_LOCALE,
        array $data = [],
        array $navigationContexts = [],
        ?string $parentUuid = null,
    ): PageInterface {
        if (null === $parentUuid) {
            $homepage = $this->findHomepage($locale);
            if (!$homepage instanceof Page) {
                throw new RuntimeException(
                    \sprintf('Cannot create page "%s": homepage not found for locale "%s".', $title, $locale),
                );
            }
            $parentUuid = $homepage->getUuid();
        }

        $pageData = [
            'locale'   => $locale,
            'title'    => $title,
            'template' => $template,
            ...$data,
        ];

        if ([] !== $navigationContexts) {
            $pageData['navigationContexts'] = $navigationContexts;
        }

        return $this->createPage(
            webspaceKey: $webspaceKey,
            data: $pageData,
            parentUuid: $parentUuid,
        );
    }
}
