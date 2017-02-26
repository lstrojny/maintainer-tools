<?php
namespace lstrojny\Maintenance\Repository;

use function Functional\filter;
use lstrojny\Maintenance\Value\Path;
use lstrojny\Maintenance\Value\Project;

class ProjectsRepository
{
    private $path;

    /** @var Project[] */
    private $projects = [];

    public function __construct($path)
    {
        $this->path = $path;
        $this->projects = array_map(
            [Project::class, 'create'],
            array_map(
                [Path::class, 'createFromRelativePath'],
                file_exists($path) ? json_decode(file_get_contents($path), true) : []
            )
        );
    }

    /** @return Project[] */
    public function matching(callable $predicate)
    {
        return filter($this->projects, $predicate);
    }

    public function add(Project $newProject): void
    {
        foreach ($this->projects as $project) {
            if ($project->compare($newProject) === 0) {
                return;
            }
        }

        $this->projects[] = $newProject;
    }

    public function flush()
    {
        file_put_contents(
            $this->path,
            json_encode(
                array_map(
                    'strval',
                    array_map(
                        function (Project $project) {
                            return $project->getPath();
                        },
                        $this->projects
                    )
                ),
                JSON_PRETTY_PRINT
            )
        );
    }
}
