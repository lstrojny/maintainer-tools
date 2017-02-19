<?php
namespace lstrojny\Maintenance\Command;

use function array_unique;
use function Functional\const_function;
use function Functional\map;
use lstrojny\Maintenance\Repository\ProjectsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ProcessBuilder;

class GitCheckoutCommand extends Command
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
            ->setName('git:checkout')
            ->setDescription('Run git checkout')
            ->addArgument('branch', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);

        $branch = $input->getArgument('branch');

        foreach ($this->projectsRepository->matching(const_function(true)) as $project) {
            $io->title(sprintf('Running git checkout %s in %s', $branch, $project->getName()));

            $process = ProcessBuilder::create(['git', 'checkout', $branch])
                ->setWorkingDirectory($project->getPath())
                ->getProcess();

            $process->enableOutput();

            $process->run(
                function ($type, $message) use ($io) {
                    $io->write($message);
                }
            );

            if (!$process->isSuccessful()) {
                $io->error(sprintf('Failed to run git'));

                return 1;
            }
        }

        return 0;
    }
}
