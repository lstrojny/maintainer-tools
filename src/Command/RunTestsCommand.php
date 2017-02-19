<?php
namespace lstrojny\Maintenance\Command;

use function Functional\const_function;
use lstrojny\Maintenance\Repository\ProjectsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ProcessBuilder;

class RunTestsCommand extends Command
{
    private $projectsRepository;

    public function __construct(ProjectsRepository $projectsRepository)
    {
        parent::__construct();
        $this->projectsRepository = $projectsRepository;
    }

    protected function configure() : void
    {
        $this
            ->setName('run:tests')
            ->setDescription('Run tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->projectsRepository->matching(const_function(true)) as $project) {
            if ($project->hasTests()) {

                $io->title(sprintf('Running tests in %s', $project->getName()));

                $process = ProcessBuilder::create(['vendor/bin/phpunit', '--colors'])
                    ->setWorkingDirectory($project->getPath())
                    ->getProcess();

                $process->enableOutput();

                $process->run(
                    function ($type, $message) use ($io) {
                        $io->write($message);
                    }
                );

                if (!$process->isSuccessful()) {
                    $io->error(sprintf('Tests failed'));

                    return 1;
                }
            }
        }

        return 0;
    }
}
