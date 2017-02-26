<?php
namespace lstrojny\Maintenance\Value;

use function Functional\first;
use function Functional\pluck;
use function Functional\partial_any;
use function Functional\compare_on;
use function Functional\sort;
use InvalidArgumentException;
use Naneau\SemVer\Parser;
use Naneau\SemVer\Sort;
use Naneau\SemVer\Version;
use RuntimeException;
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

    public static function create(Path $path): Project
    {
        return new static($path);
    }

    public function getName(): string
    {
        return $this->usesComposer() ? $this->composer()->name : basename($this->path);
    }

    public function composer(): TreeNode
    {
        return new TreeNode(json_decode(file_get_contents($this->path->file('composer.json')), true));
    }

    public function usesComposer(): bool
    {
        return $this->path->exists('composer.json');
    }

    public function travis(): TreeNode
    {
        return new TreeNode(Yaml::parse(file_get_contents($this->path->file('.travis.yml'))));
    }

    /** @return string[] */
    public function getTravisVersions(): array
    {
        return sort(
            array_unique(
                $this->travis()->php->get() ?? pluck($this->travis()->matrix->include->get() ?? [], 'php')
            ),
            compare_on('strcmp')
        );
    }

    public function hasTests(): bool
    {
        return $this->path->exists('phpunit.xml.dist') && $this->path->exists('vendor/bin/phpunit');
    }

    public function git(...$args): string
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
                    function (array $vs)
                    {
                        return $vs[0] === 'origin';
                    }
                )[1]
            )[1]
        );
    }

    public function needsRelease(): bool
    {
        return !empty($this->git('diff', $this->getLatestVersion()->getOriginalVersion()));
    }

    public function getLatestVersion(): Version
    {
        return first(array_reverse($this->getVersions()));
    }

    public function getLatestRemoteVersion(): Version
    {
        return array_reverse($this->getRemoteVersions())[0];
    }

    public function latestReleasePublished(): bool
    {
        return $this->getLatestVersion()->getOriginalVersion()
            === $this->getLatestRemoteVersion()->getOriginalVersion();
    }

    public function hasLocalChanges(): bool
    {
        return !empty($this->git('diff')) || !empty($this->git('diff', 'HEAD'));
    }

    /** @return Version[] */
    public function getVersions()
    {
        return Sort::sortArray(array_map([__CLASS__, 'parseVersion'], array_filter(explode("\n", $this->git('tag')))));
    }

    /** @return Version[] */
    public function getRemoteVersions()
    {
        $versions = pluck(
            array_map(
                partial_any('explode', '/', …),
                        array_filter(
                            pluck(
                                array_map(
                                    partial_any('preg_split', '/\s+/', …),
                                    array_filter(explode("\n", $this->git('ls-remote', 'origin', 'refs/tags/*')))
                                ),
                                1
                            ),
                            function ($version) {
                                return $version[-1] !== '}';
                            }
                        )
                    ),
                    2
                );

        return Sort::sortArray(array_map([__CLASS__, 'parseVersion'], $versions));
    }

    public static function parseVersion($version): Version
    {
        $fixedVersion = preg_replace('/^v/', '', $version);
        $fixedVersion = preg_replace('/(\d)(alpha|beta|gamma|rc)/', '$1-$2', $fixedVersion);

        try {
            $parsed = Parser::parse($fixedVersion);
            $parsed->setOriginalVersion($version);

            return $parsed;
        } catch (InvalidArgumentException $e) {

            switch (substr_count($fixedVersion, '.')) {
                case 1: // 0.1
                    $fixedVersion .= '.0';
                    break;

                case 3: // 0.2.0.0
                    if ($fixedVersion[-2] !== '.') {
                        throw new RuntimeException(sprintf('Invalid version: "%s"', $version));
                    }
                    $fixedVersion = substr($fixedVersion, 0, -2);
                    break;

                default:
                    throw new RuntimeException(sprintf('Unhandled version: "%s"', $version));
            }

            $parsed = Parser::parse($fixedVersion);
            $parsed->setOriginalVersion($version);

            return $parsed;
        }
    }

    public function compare(self $other): int
    {
        return $other->path->compare($this->path);
    }

    public function getPath(): Path
    {
        return $this->path;
    }
}
