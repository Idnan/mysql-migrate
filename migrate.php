<?php

/**
 * Class Migrate
 */
class Migrate
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $migration;

    /**
     * @var PDO
     */
    private $conn;

    /**
     * Migration version file
     */
    const MIGRATE_VERSION_FILE = '.version';

    /**
     * Migration file prefix
     */
    const MIGRATE_FILE_PREFIX = 'migrate-';

    /**
     * Migration file postfix
     */
    const MIGRATE_FILE_POSTFIX = '.sql';

    /**
     * Migrate constructor.
     *
     * @param $migration string
     */
    public function __construct($migration)
    {
        $this->migration = $migration;
        $this->config    = include_once 'config.php';

        $this->connect();
    }

    /**
     * Connect to database
     */
    public function connect()
    {
        $host     = $this->config['host'];
        $username = $this->config['username'];
        $password = $this->config['password'];
        $port     = $this->config['port'];
        $database = $this->config['database'];

        $this->conn = @(new mysqli($host, $username, $password, $database, $port));

        if (!empty($this->conn->connect_error)) {
            echo "Failed to connect to the database." . PHP_EOL;
            exit(1);
        }
    }

    /**
     * Make migration
     */
    public function make()
    {
        $migrationDir = $this->getMigrationDirectory();
        $version      = $this->getCurrentVersion();

        echo "Current database version is: $version\n";

        $new_version = $version;
        // Check the new version against existing migrations.
        $files     = $this->getMigrations();
        $last_file = end($files);
        if ($last_file !== false) {
            $file_version = $this->getVersionFromFile($last_file);
            if ($file_version > $new_version)
                $new_version = $file_version;
        }
        // Create migration file path.
        $new_version++;

        if (!file_exists($migrationDir)) {
            mkdir('migrations/', 0777);
        }

        $path = $migrationDir . static::MIGRATE_FILE_PREFIX . sprintf('%04d', $new_version);
        $path .= '-' . str_replace(' ', '-', $this->migration);
        $path .= static::MIGRATE_FILE_POSTFIX;

        echo "Adding a new migration script: $path" . PHP_EOL;
        $f = @fopen($path, 'w');
        if ($f) {
            fputs($f, "## WRITE YOU QUERY HERE...");
            fclose($f);
            echo "Done." . PHP_EOL;
        } else {
            echo "Failed." . PHP_EOL;
        }
    }

    /**
     * Run migrations
     */
    public function run()
    {
        $files        = $this->getMigrations();
        $version      = $this->getCurrentVersion();
        $migrationDir = $this->getMigrationDirectory();

        // Check to make sure there are no conflicts such as 2 files under the same version.
        $errors       = [];
        $last_file    = false;
        $last_version = false;
        foreach ($files as $file) {
            $file_version = $this->getVersionFromFile($file);
            if ($last_version !== false && $last_version === $file_version) {
                $errors[] = "$last_file --- $file";
            }
            $last_version = $file_version;
            $last_file    = $file;
        }
        if (count($errors) > 0) {
            echo "Error: You have multiple files using the same version. " .
                 "To resolve, move some of the files up so each one gets a unique version." . PHP_EOL;
            foreach ($errors as $error) {
                echo "  $error" . PHP_EOL;
            }
            exit;
        }

        // Run all the new files.
        $found_new = false;
        foreach ($files as $file) {
            $file_version = $this->getVersionFromFile($file);
            if ($file_version <= $version) {
                continue;
            }

            echo "Running: $file" . PHP_EOL;
            $query = file_get_contents($migrationDir . $file);
            $this->query($query);
            echo "Done." . PHP_EOL;

            $version   = $file_version;
            $found_new = true;
            // Output the new version number.
            $f = fopen(static::MIGRATE_VERSION_FILE, 'w');
            if ($f) {
                fputs($f, $version);
                fclose($f);
            } else {
                echo "Failed to output new version to " . static::MIGRATE_VERSION_FILE . PHP_EOL;
            }
        }
        if ($found_new) {
            echo "Migration complete." . PHP_EOL;
        } else {
            echo "Your database is up-to-date." . PHP_EOL;
        }
    }

    /**
     * Return current migration number
     *
     * @return int
     *
     */
    private function getCurrentVersion()
    {
        $version = 0;
        $f       = @fopen(static::MIGRATE_VERSION_FILE, 'r');
        if ($f) {
            $version = intval(fgets($f));
            fclose($f);
        }

        return $version;
    }

    /**
     * Query Database
     *
     * @param $query
     *
     * @return bool
     */
    private function query($query)
    {
        $result = $this->conn->query($query);
        if (!$result) {
            echo "Migration failed: " . $this->conn->errorInfo() . "\n";
            echo "Aborting.\n";
            $this->conn->rollBack();
            exit;
        }

        return true;
    }

    /**
     * Find all the migration files in the directory and return the sorted.
     *
     * @return array
     */
    private function getMigrations()
    {
        $files = [];
        $dir   = @opendir($this->getMigrationDirectory());
        while ($file = @readdir($dir)) {
            if (substr($file, 0, strlen(static::MIGRATE_FILE_PREFIX)) == static::MIGRATE_FILE_PREFIX) {
                $files[] = $file;
            }
        }
        asort($files);

        return $files;
    }

    /**
     * Return version from file
     *
     * @param $file
     *
     * @return int
     */
    private function getVersionFromFile($file)
    {
        return intval(substr($file, strlen(static::MIGRATE_FILE_PREFIX)));
    }

    /**
     * Return migration directory
     *
     * @return string
     */
    private function getMigrationDirectory()
    {
        return $this->config['migrations_dir'];
    }
}

$command   = !empty($argv[1]) ? strtolower($argv[1]) : 'invalid';
$migration = !empty($argv[2]) ? strtolower($argv[2]) : '';

if (count($argv) <= 1 ||
    !in_array($command, ['make', 'run']) ||
    ($command == 'make' && empty($migration))
) {
    echo "Usage:
     To add new migration:
         php php-mysql-migrate/migrate.php make <name-without-spaces>
     To migrate your database:
         php php-mysql-migrate/migrate.php migrate
     " . PHP_EOL;
    exit;
}

$migration = new Migrate($migration);
$migration->$command();