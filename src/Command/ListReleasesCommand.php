<?php
namespace lstrojny\Maintenance\Command;

use function Functional\compare_on;
use function Functional\const_function;
use InvalidArgumentException;
use lstrojny\Maintenance\Repository\ProjectsRepository;
use Naneau\SemVer\Parser;
use Naneau\SemVer\Sort as VersionSorter;
use Naneau\SemVer\Version;
use RuntimeException;
use function substr_count;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListReleasesCommand extends Command
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
            ->setName('release:list')
            ->setDescription('Tag next release information')
            ->addArgument('type')
            ->addOption('newest', null, InputOption::VALUE_NONE, 'Only show newest version');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $rows = [];


        foreach ($this->projectsRepository->matching(const_function(true)) as $project) {
            foreach (array_reverse($project->getVersions()) as $version) {
                $rows[] = [$project->getName(), (string) $version, $version->getOriginalVersion()];

                if ($input->getOption('newest')) {
                    break;
                }
            }
        }

        $io = new SymfonyStyle($input, $output);

        $io->table(['Project', 'Version', 'GIT Tag'], $rows);

        return 0;
    }
}
