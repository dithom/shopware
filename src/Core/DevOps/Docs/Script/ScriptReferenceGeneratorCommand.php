<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs\Script;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 *
 * @package core
 */
#[AsCommand(
    name: 'docs:generate-scripts-reference',
    description: 'Generate the script reference',
)]
class ScriptReferenceGeneratorCommand extends Command
{
    /**
     * @param iterable<ScriptReferenceGenerator> $generators
     */
    public function __construct(private iterable $generators)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Generating reference documentation for the app scripts feature.');

        foreach ($this->generators as $generator) {
            foreach ($generator->generate() as $file => $content) {
                file_put_contents($file, $content);
            }
        }

        $io->success('Reference documentation was generated successfully');

        return self::SUCCESS;
    }

    protected function configure(): void
    {
        $this->setDescription('(Re-)Generate reference documentation for the app scripts feature.');
    }
}