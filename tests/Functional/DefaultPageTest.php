<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Factory\Form\FormFactory;
use App\Factory\Pages\PageFactory;
use App\Tests\WebsiteTestCase;
use Symfony\Component\HttpFoundation\Request;

class DefaultPageTest extends WebsiteTestCase
{
    private PageFactory $pageFactory;
    private FormFactory $formFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();

        $pageFactory = $container->get(PageFactory::class);
        \assert($pageFactory instanceof PageFactory);
        $this->pageFactory = $pageFactory;

        $formFactory = $container->get(FormFactory::class);
        $this->assertInstanceOf(FormFactory::class, $formFactory);
        $this->formFactory = $formFactory;
    }

    public function testPageWithFormRendersSuccessfully(): void
    {
        // 1. Create a valid test form via the built-in Sulu command
        $form = $this->formFactory->createGeneratedForm();

        // 2. Create and publish a page that references the form
        $this->pageFactory->createAndPublish(
            title: 'Form Test Page',
            template: 'default',
            data: ['form' => $form->getId()],
        );

        // Clear entity manager to ensure changes are flushed
        $this->getEntityManager()->clear();

        // 3. Request the page
        $this->client->request(Request::METHOD_GET, '/form-test-page');

        // 4. Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}
