<?php
namespace lstrojny\Maintenance\Command;

use lstrojny\Maintenance\Repository\ProjectsRepository;
use lstrojny\Maintenance\Value\Path;
use lstrojny\Maintenance\Value\Project;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddProjectCommand extends Command
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
            ->setName('projects:add')
            ->setDescription('Add a project to the list of maintained projects')
            ->addArgument('path', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $project = Project::create(Path::createFromRelativePath($input->getArgument('path')));

        $this->projectsRepository->add($project);

        $this->projectsRepository->flush();

        return 0;
    }
}
