<?php
namespace lstrojny\Maintenance\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use lstrojny\Maintenance\Repository\ProjectsRepository;
use lstrojny\Maintenance\Value\StatusEmojis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function GuzzleHttp\Promise\unwrap;

class ProjectsInformationCommand extends Command
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
            ->setName('projects:info')
            ->setDescription('Show projects information')
            ->addOption('quick');

        $this->configureFilterOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        $http = new Client();

        $requests = [];
        foreach ($this->projectsRepository->matching(self::createProjectMatcher($input)) as $project) {
            $requests[$project->getName()] = $http->getAsync(
                'https://api.travis-ci.org/repos/' . $project->getGitHubRepositoryName()
            );
        }

        /** @var Response[] $response */
        $responses = unwrap($requests);

        $io->progressStart(count($this->projectsRepository->matching(self::createProjectMatcher($input))));

        foreach ($this->projectsRepository->matching(self::createProjectMatcher($input)) as $project) {

            $io->progressAdvance();

            $response = $responses[$project->getName()];

            $result = json_decode($response->getBody(), true);

            if (!$result) {
                $buildResult = ' - ';
            } else {

                if ($result['last_build_result'] === 0) {
                    $status = StatusEmojis::POSITIVE;
                } elseif ($result['last_build_result'] === null) {
                    $status = StatusEmojis::PROGRESS;
                } else {
                    $status = StatusEmojis::NEGATIVE;
                }

                $buildResult = $status . '   https://travis-ci.org/' . $project->getGitHubRepositoryName();
            }

            if ($project->needsRelease()) {
                $releaseStatus = StatusEmojis::NEGATIVE;
            } elseif ($input->getOption('quick') || $project->latestReleasePublished()) {
                $releaseStatus = StatusEmojis::POSITIVE;
            } else {
                $releaseStatus = StatusEmojis::PROGRESS;
            }

            $rows[] = [
                $project->getName(),
                $releaseStatus . '   ' . $project->getLatestVersion(),
                ($project->hasLocalChanges() ? StatusEmojis::PROGRESS : StatusEmojis::POSITIVE),
                $project->usesComposer() ? $project->composer()->require->php : 'n.A.',
                implode(' ', $project->getTravisVersions()),
                $buildResult
            ];
        }

        $io->progressFinish();

        $io->table(
            ['Project', 'Latest version', "Pend. changes", 'PHP', 'Travis PHP versions', 'Build status'],
            $rows
        );

        return 0;
    }
}
