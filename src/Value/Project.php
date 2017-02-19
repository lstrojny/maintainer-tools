<?php
namespace lstrojny\Maintenance\Value;

use function file_get_contents;
use function Functional\first;
use function Functional\partial_any;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Yaml;
use const Functional\…;

class Project
{
    private $path;

    private function __construct(Path $path)
    {
        $this->path = $path;
    }

    public static function create(Path $path) : Project
    {
        return new static($path);
    }

    public function getName() : string
    {
        return $this->usesComposer() ? $this->composer()->name : basename($this->path);
    }

    public function composer() : TreeNode
    {
        return new TreeNode(json_decode(file_get_contents($this->path->file('composer.json')), true));
    }

    public function usesComposer() : bool
    {
        return $this->path->exists('composer.json');
    }

    public function travis() : TreeNode
    {
        return new TreeNode(Yaml::parse(file_get_contents($this->path->file('.travis.yml'))));
    }

    public function hasTests() : bool
    {
        return $this->path->exists('phpunit.xml.dist') && $this->path->exists('vendor/bin/phpunit');
    }

    public function git(...$args) : string
    {
        $process = ProcessBuilder::create(array_merge(['git'], $args))
            ->setWorkingDirectory($this->path)
            ->getProcess();

        $process->run();

        return $process->getOutput();
    }

    public function getGitHubRepositoryName()
    {
        return str_replace(
            '.git',
            '',
            explode(
                ':',
                first(
                    array_map(
                        partial_any('preg_split', '/\s+/', …),
                        array_filter(explode("\n", $this->git('remote', '-v')))
                    ),
                    function (array $vs) {
                        return $vs[0] === 'origin';
                    }
                )[1]
            )[1]
        );
    }

    public function compare(self $other) : int
    {
        return $other->path->compare($this->path);
    }

    public function getPath() : Path
    {
        return $this->path;
    }
}
