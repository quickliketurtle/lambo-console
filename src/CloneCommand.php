<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class CloneCommand extends Command
{
    protected function configure()
    {
        $this->setName('clone')
            ->setDescription('Clone a project')
            ->addArgument(
                'projectName', InputArgument::REQUIRED, 'What is the project name? i.e. tightenco/lambo'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        list($projectVendor, $projectName) = explode("/", $input->getArgument('projectName'));

        $io->title("Cloning {$projectName}");

        // Clone Project
        $io->section("Cloaning Project");

        $git = new Process("git clone https://github.com/{$projectVendor}/{$projectName}.git");
        $git->run();

        $output->writeln($git->getOutput());

        // CD into project directory
        chdir($projectName);

        // Install composer dedendences
        $io->section("Installing composer dependencies");

        $composer = new Process("composer install");
        $composer->run();

        // Install node dependencies
        $io->section("Installing node dependencies");

        $yarn = new Process('yarn');
        $yarn->run();

        // Copy .env-example to .env
        $io->section("Creating .env file");

        copy('.env.example', '.env');

        // Generate app key
        $io->section("Generating app key");

        $key = new Process('php artisan key:generate');
        $key->run();

        // Migrate Database
        $io->section("Migrating database");

        $migrate = new Process('php artisan migrate');
        $migrate->run();

        // Seed Database
        $io->section("Seeding database");

        $seed = new Process('php artisan db:seed');
        $seed->run();

        // Open Site

        // Get tld from valet config
        $tld = json_decode(file_get_contents("{$_SERVER['HOME']}/.valet/config.json"))->domain;

        $projectUrl = "http://{$projectName}.{$tld}";

        $openUrl = new Process("open {$projectUrl}");
        $openUrl->run();

        $io->success("Project cloned successfully. Remember to cd into {$projectName} before you start editing.");
    }
}
