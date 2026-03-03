<?php

declare(strict_types=1);

namespace App\Tests;

use App\Factory\Form\FormFactory;
use App\Factory\Pages\PageFactory;
use Sulu\Bundle\TestBundle\Testing\WebsiteTestCase as BaseWebsiteTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

abstract class WebsiteTestCase extends BaseWebsiteTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createWebsiteClient([], ['HTTPS' => 'true']);

        $this->purgeDatabase();
        $this->initializeHomepage();
    }

    private function initializeHomepage(): void
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput(['command' => 'sulu:page:initialize', '--env' => 'test']),
            new NullOutput(),
        );
    }
}
