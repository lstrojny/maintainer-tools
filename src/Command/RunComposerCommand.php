<?php
namespace lstrojny\Maintenance\Command;

use lstrojny\Maintenance\Repository\ProjectsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class RunComposerCommand extends Command
{
    use FilterableProjectsCommandTrait;

    private $projectsRepository;

    public function __construct(ProjectsRepository $projectsRepository)
    {
        parent::__construct();
        $this->projectsRepository = $projectsRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('composer:run')
            ->setDescription('Run composer command')
            ->addArgument('composer-command', InputArgument::REQUIRED);

        $this->configureFilterOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $command = $input->getArgument('composer-command');

        foreach ($this->projectsRepository->matching(self::createProjectMatcher($input)) as $project) {
            if ($project->usesComposer()) {

                $io->title(sprintf('Running composer %s in %s', $command, $project->getName()));


                $process = new Process('composer.phar ' . $command);
                $process->setWorkingDirectory($project->getPath());
                $process->enableOutput();

                $process->run(
                    function ($type, $message) use ($io) {
                        $io->write($message);
                    }
                );

                if (!$process->isSuccessful()) {
                    $io->error(sprintf('Command failed'));

                    return 1;
                }
            }
        }

        return 0;
    }
}
