<?php
namespace lstrojny\Maintenance\Command;

use function Functional\const_function;
use function Functional\first;
use function Functional\partial_any;
use function Functional\pluck;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use function GuzzleHttp\Promise\unwrap;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\UriTemplate;
use lstrojny\Maintenance\Repository\ProjectsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectsInformationCommand extends Command
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
            ->setName('projects:info')
            ->setDescription('Show projects information');
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];

        $http = new Client();

        $requests = [];
        foreach ($this->projectsRepository->matching(const_function(true)) as $project) {
            $requests[$project->getName()] = $http->getAsync(
                'https://api.travis-ci.org/repos/' . $project->getGitHubRepositoryName()
            );
        }

        /** @var Response[] $response */
        $responses = unwrap($requests);

        foreach ($this->projectsRepository->matching(const_function(true)) as $project) {

            $response = $responses[$project->getName()];

            $result = json_decode($response->getBody(), true);

            if (!$result) {
                $buildResult = ' - ';
            } else {
                $buildResult = ($result['last_build_result'] === 0 ? '❎' : '❌')
                    . '   https://travis-ci.org/' . $project->getGitHubRepositoryName();
            }

            var_dump($project->getGitHubRepositoryName());


            $rows[] = [
                $project->getName(),
                $project->usesComposer() ? $project->composer()->require->php : 'n.A.',
                implode(' ', $project->travis()->php->get()),
                $buildResult
            ];
        }

        $io->table(
            ['Project', 'Composer PHP version', 'Travis build PHP versions', 'Build status'],
            $rows
        );

        return 0;
    }
}
