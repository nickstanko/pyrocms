<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$targets = [
    $root . '/vendor/anomaly',
];

$replacements = [
    '$this->dispatch(' => '$this->dispatchSync(',
    'dispatch_now(' => 'dispatch_sync(',
    'public function __construct(UrlGenerator $url = null, Factory $view)' => 'public function __construct(?UrlGenerator $url, Factory $view)',
    'public function __construct(HtmlBuilder $html, UrlGenerator $url, Factory $view, $csrfToken, Request $request = null)' => 'public function __construct(HtmlBuilder $html, UrlGenerator $url, Factory $view, $csrfToken, ?Request $request = null)',
];

$migrateCommandPath = $root . '/vendor/anomaly/streams-platform/src/Database/Migration/Console/MigrateCommand.php';
$migrateCommandContents = <<<'PHP'
<?php namespace Anomaly\Streams\Platform\Database\Migration\Console;

use Anomaly\Streams\Platform\Database\Migration\Console\Command\ConfigureMigrator;
use Anomaly\Streams\Platform\Database\Migration\Console\Command\MigrateAllAddons;
use Anomaly\Streams\Platform\Database\Migration\Console\Command\MigrateStreams;
use Anomaly\Streams\Platform\Database\Migration\Migrator;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Symfony\Component\Console\Input\InputOption;

class MigrateCommand extends \Illuminate\Database\Console\Migrations\MigrateCommand
{
    use DispatchesJobs;

    protected $migrator;

    protected $signature = 'migrate {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}
                {--graceful : Return a successful exit code even if an error occurs}
                {--addon= : The addon to migrate}
                {--streams : Flag all streams core/application for migration}
                {--all-addons : Flag all addons for migration}';

    public function handle()
    {
        if ($this->input->getOption('streams')) {
            return dispatch_sync(new MigrateStreams($this));
        }

        if ($this->input->getOption('all-addons')) {
            return dispatch_sync(new MigrateAllAddons($this));
        }

        dispatch_sync(
            new ConfigureMigrator(
                $this,
                $this->input,
                $this->migrator
            )
        );

        return parent::handle();
    }

    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            [
                ['addon', null, InputOption::VALUE_OPTIONAL, 'The addon to migrate.'],
                ['streams', null, InputOption::VALUE_NONE, 'Flag all streams core/application for migration.'],
                ['all-addons', null, InputOption::VALUE_NONE, 'Flag all addons for migration.'],
            ]
        );
    }
}
PHP;

foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if ($contents === false) {
            fwrite(STDERR, "Failed to read {$path}\n");
            exit(1);
        }

        $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);

        if ($updated === $contents) {
            continue;
        }

        if (file_put_contents($path, $updated) === false) {
            fwrite(STDERR, "Failed to write {$path}\n");
            exit(1);
        }
    }
}

if (is_file($migrateCommandPath) && file_put_contents($migrateCommandPath, $migrateCommandContents) === false) {
    fwrite(STDERR, "Failed to write {$migrateCommandPath}\n");
    exit(1);
}

echo "Applied legacy Anomaly compatibility patches.\n";
