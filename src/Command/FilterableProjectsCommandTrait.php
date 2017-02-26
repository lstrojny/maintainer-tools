<?php
namespace lstrojny\Maintenance\Command;

use lstrojny\Maintenance\Value\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Functional\const_function;

trait FilterableProjectsCommandTrait
{
    protected function configureFilterOptions(): void
    {
        $this
            ->addOption('select-regex', null, InputOption::VALUE_REQUIRED, 'Select projects matching regex')
            ->addOption('reject-regex', null, InputOption::VALUE_REQUIRED, 'Reject projects matching regex');
    }

    protected static function createProjectMatcher(InputInterface $input)
    {
        $selector = $input->getOption('select-regex');
        $rejector = $input->getOption('reject-regex');

        return $selector || $rejector
            ? function (Project $project) use ($selector, $rejector) {
                return (!$selector || preg_match(self::filterRegex($selector), $project->getName()))
                    && (!$rejector || !preg_match(self::filterRegex($rejector), $project->getName()));
            }
            : const_function(true);
    }

    private static function filterRegex(string $regex): string
    {
        return '@' . str_replace('@', '\@', $regex) . '@';
    }
}
