<?php
namespace lstrojny\Maintenance\Command;

use lstrojny\Maintenance\Repository\ProjectsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_unique;
use function Functional\map;

class ShowComposerVersionsCommand extends Command
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
            ->setName('composer:dependencies')
            ->setDescription('Show composer dependencies');

        $this->configureFilterOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $packages = [];

        foreach ($this->projectsRepository->matching(self::createProjectMatcher($input)) as $project) {

            if ($project->usesComposer()) {
                $dependencies = array_merge(
                    $project->composer()->require->get() ?: [],
                    $project->composer()->{'require-dev'}->get() ?: []
                );
                foreach ($dependencies as $package => $version) {
                    if (!isset($packages[$package])) {
                        $packages[$package] = [];
                    }

                    $packages[$package][$project->getName()] = $version;
                }
            }
        }

        $io->table(
            ['Package', 'Cardinality', 'Versions'],
            map(
                $packages,
                function (array $versions, $package) {
                    return [
                        $package,
                        count(array_unique($versions)),
                        implode(
                            "\n",
                            map(
                                $versions,
                                function ($version, $package) {
                                    return sprintf('%s: %s', $package, $version);
                                }
                            )
                        ),
                    ];
                }
            )
        );

        return 0;
    }
}
