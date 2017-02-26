<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class CreateCommand extends Command
{
    protected function configure()
    {
        $this->setName('create')
            ->setDescription('Create new project')
            ->addArgument(
                'projectName', InputArgument::REQUIRED, 'What is the project name?'
            )
            ->addOption(
                'path', 'p', InputOption::VALUE_OPTIONAL, 'What is the project path?', '.'
            )
            ->addOption(
                'message', 'm', InputOption::VALUE_OPTIONAL, 'Message for initial git commit?', 'Initial commit.'
            )
            ->addOption(
                'editor', 'e', InputOption::VALUE_OPTIONAL, 'Preferred Editor?'
            )
            ->addOption(
                'dev', 'd', InputOption::VALUE_NONE, 'Run laravel new with --dev option?', null
            )
            ->addOption(
                'auth', 'a', InputOption::VALUE_NONE, 'Scaffold Laravel Auth?', null
            )
            ->addOption(
                // @TODO Can not use 'n' as it is already used by console component.
                'node', null, InputOption::VALUE_NONE, 'Install node dependencies?', null
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title("Creating new Laravel app {$input->getArgument('projectName')}");

        // CD to Install Path
        chdir($input->getOption('path'));

        // Run laravel installer
        $io->section("Creating new Laravel project");

        $laravel = $this->getApplication()->find('new');

        $arguments = [
            'name' => $input->getArgument('projectName'),
            '--dev' => $input->getOption('dev'),
        ];

        $laravelInput = new ArrayInput($arguments);
        $laravel->run($laravelInput, $io);

        // CD to Project Directory
        chdir($input->getArgument('projectName'));

        // Install Node Dependencies
        if ($input->getOption('node')) {
            $io->section("Installing Node Dependancies");

            $yarntest = new Process("which yarn");
            $yarntest->run();

            if ($yarntest->isSuccessful()) {
                $yarn = new Process('yarn');
                $yarn->run(function ($type, $buffer) use ($io) {
                    if (Process::ERR === $type) {
                        $io->error($buffer);
                    } else {
                        $io->text($buffer);
                    }
                });
            } else {
                $npm = new Process('npm install');
                $npm->run(function ($type, $buffer) use ($io) {
                    if (Process::ERR === $type) {
                        $io->error($buffer);
                    } else {
                        $io->text($buffer);
                    }
                });
            };
        }

        // Scaffold Laravel Authentication
        if ($input->getOption('auth')) {
            $io->section("Scaffolding Laravel Authentication");

            $makeAuth = new Process('php artisan make:auth');
            $makeAuth->run();

            $output->writeln($makeAuth->getOutput());
        }

        // Initialize Git Repo
        $io->section("Initializing Git Repo");

        $git = new Process("git init; git add .; git commit -m '{$input->getOption('message')}';");
        $git->run();

        $output->writeln($git->getOutput());

        // Open Editor
        if ($input->getOption('editor')) {
            $io->section("Opening project in Editor");

            $editor = new Process("{$input->getOption('editor')} .");
            $editor->run();
        }

        // Update .env to point to this database with `root` username and blank pw,
        // like Mac MySQL defaults, and appropriate domain

        // Get tld from valet config
        $tld = json_decode(file_get_contents("{$_SERVER['HOME']}/.valet/config.json"))->domain;

        $projectUrl = "http://{$input->getArgument('projectName')}.{$tld}";

        $sedCommands = [
            "/DB_DATABASE/s/homestead/{$input->getArgument('projectName')}/",
            '/DB_USERNAME/s/homestead/root/',
            '/DB_PASSWORD/s/secret//',
            "/APP_URL/s/localhost/{$input->getArgument('projectName')}\.{$tld}/"
        ];

        // @TODO check for linux and update sed command
        $io->section("Updating .env config");
        foreach ($sedCommands as $sedCommand) {
            $sed = new Process("sed -i '' {$sedCommand} .env");
            $sed->run();
        }

        $openUrl = new Process("open {$projectUrl}");
        $openUrl->run();

        $io->success("Your're ready to go! Remember to cd into {$input->getOption('path')}/{$input->getArgument('projectName')} before you start editing.");

        // @TODO Open new console in project directory
    }
}
