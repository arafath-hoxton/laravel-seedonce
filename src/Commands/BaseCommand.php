<?php

namespace Ranium\SeedOnce\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ranium\SeedOnce\Repositories\SeederRepositoryInterface as Repository;

class BaseCommand extends Command
{
    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $container;

    /**
     * Seeder repository
     *
     * @var \Ranium\SeedOnce\Repositories\SeederRepositoryInterface
     */
    protected $repository;

    /**
     * Create a new database seed command instance.
     *
     * @param \Illuminate\Database\ConnectionResolverInterface $resolver
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Illuminate\Container\Container $container
     * @param \Ranium\SeedOnce\Repositories\SeederRepositoryInterface $repository
     * @return void
     */
    public function __construct(Resolver   $resolver,
                                Filesystem $files,
                                Container  $container,
                                Repository $repository)
    {
        parent::__construct();

        $this->resolver = $resolver;
        $this->files = $files;
        $this->container = $container;
        $this->repository = $repository;
    }

    /**
     * Get the seeders to mark as seeded.
     * NOTE: Main Database Seeder is always excluded.
     *
     * @param string $classOption Which class to get. "all" for all classes.
     * @return array
     */
    protected function getSeeders($classOption = 'all')
    {
        // Read all files from the database/seeds directory
        $seedersPath = $this->getSeederFolder();
        // get all subdirectories in the seeds directory
        $seederDir = $this->files->directories($seedersPath);
        // add DIRECTORY_SEPARATOR to the end of the path
        $seederDirMapped = array_map(function ($path) {
            return $path . DIRECTORY_SEPARATOR;
        }, $seederDir);
        $seedersPath = array_merge($seederDirMapped, [$seedersPath]);



        return Collection::make($seedersPath)
            ->flatMap(function ($path) {


                return Str::endsWith($path, '.php') ? [$path] : $this->files->glob($path . '*.php');
            })
            ->map(function ($path) {
                return $this->getNamespace($path);
            })
            ->filter(function ($class) use ($classOption) {
                // Filter out classes based on option passed
                return ($classOption === 'all' || $classOption === $class)
                    // We want to skip DatabaseSeeder as we never mark it as seeded
                    && class_basename($class) !== class_basename(config('seedonce.database_seeder'));
            });
    }

    /**
     * @return string
     */
    protected function getSeederFolder()
    {
        return $this->laravel->databasePath() . DIRECTORY_SEPARATOR . config('seedonce.folder_seeder') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the name of the seeder class.
     *
     * @param string $path
     * @return string
     */
    protected function getSeederName($path)
    {
        return $this->getSeederNamespace() . str_replace('.php', '', class_basename($path));
    }

    /**
     * @return string
     */
    protected function getSeederNamespace()
    {
        $composerJsonPath = base_path('composer.json');
        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        $seederConfigPath = config('seedonce.folder_seeder');

        if ((float)app()->version() >= 8) {
            $items = array_filter($composerConfig['autoload']['psr-4'], function ($item) use ($seederConfigPath) {
                return Str::contains($item, $seederConfigPath);
            });

            return array_keys($items)[0] ?? '';
        }

        return '';
    }

    /**
     * Get the name of the database connection to use.
     *
     * @return string
     */
    protected function getDatabase()
    {
        $database = $this->input->getOption('database');

        return $database ?: $this->laravel['config']['database.default'];
    }

    public function getNamespace($path) {
        $file = file_get_contents($path);
        $namespace = "";
        $tokens = token_get_all($file);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j] === ';') {
                        break;
                    }
                    $namespace .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
            }
        }
        // add the class name
        $namespace = $namespace . '\\' . basename($path, '.php');


        return trim($namespace);
    }
}
