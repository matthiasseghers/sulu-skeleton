<?php

declare(strict_types=1);

namespace App\Factory\Form;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Sulu\Bundle\FormBundle\Entity\Form;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Factory\BaseSuluFactory;

/**
 * Factory for creating Sulu dynamic forms in tests and fixtures.
 *
 * Uses the built-in `sulu:form:generate-form` command which creates a validated
 * "Test Form" entity — avoiding manual entity wiring that is fragile across versions.
 */
class FormFactory
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Run sulu:form:generate-form and return the created/updated Form entity.
     * The command is idempotent: it creates or updates a form titled "Test Form".
     */
    public function createGeneratedForm(): Form
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput(['command' => 'sulu:form:generate-form']),
            new NullOutput(),
        );

        $form = $this->findByTitle('Test Form');

        if (!$form instanceof Form) {
            throw new RuntimeException('sulu:form:generate-form ran but "Test Form" was not found in the database.');
        }

        return $form;
    }

    public static function create(int $formId, array $overrides = []): array
    {
        $defaults = [
            'type' => 'form',
            'form' => $formId,
        ];

        return \array_merge($defaults, $overrides);
    }

    public function findByTitle(string $title, string $locale = 'en'): ?Form
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('f')
            ->from(Form::class, 'f')
            ->join('f.translations', 't')
            ->where('t.title = :title')
            ->andWhere('t.locale = :locale')
            ->setParameter('title', $title)
            ->setParameter('locale', $locale)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
