<?php
namespace lstrojny\Maintenance\Value;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use const DIRECTORY_SEPARATOR;

class Path
{
    private $path;

    private function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('Invalid path: "%s"', $path));
        }

        $this->path = $path;
    }

    public static function createFromRelativePath(string $path): Path
    {
        $fs = new Filesystem();

        if (!$fs->isAbsolutePath($path)) {
            $path = realpath(
                rtrim(getcwd(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim($path, DIRECTORY_SEPARATOR)
            );
        }

        return new static($path);
    }

    public function file(string $file): Path
    {
        return new self(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file);
    }

    public function exists(string $file): bool
    {
        return file_exists($this->path . DIRECTORY_SEPARATOR . $file);
    }

    public function compare(Path $other): int
    {
        return $other->path <=> $this->path;
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
