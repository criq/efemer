<?php

namespace App\Classes\Jobs;

use Katu\Tools\Jobs\Job;
use Katu\Types\TClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunFromCLI extends Command
{
	public function getName(): string
	{
		return "jobs:run";
	}

	public function getDescription(): string
	{
		return "Run a job";
	}

	protected function configure(): void
	{
		$this->addArgument("class", InputArgument::REQUIRED, "Job class portable name (backslashes as dots), e.g. App.Classes.Jobs.Ingest.RefreshEfemerCz");
		$this->addOption("force", "f", InputOption::VALUE_NONE, "Run even if a lock exists; also raise load-average gate.");
		$this->addOption("limit", "l", InputOption::VALUE_REQUIRED, "Limit items to process (jobs that support it).");
		$this->addOption("args", null, InputOption::VALUE_OPTIONAL, "JSON object of job arguments.");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// docker compose exec -T has no TTY; still emit ANSI for local terminals that support it
		$output->setDecorated(true);
		Job::setIgnoreMaxLoadAverage(true);

		Signals::install(static function () use ($output): void {
			$output->writeln("");
			$output->writeln(ConsoleStyle::warn("Interrupted — stopping job."));
			exit(130);
		});

		$class = TClass::createFromPortableName($input->getArgument("class"));
		if (!$class->exists()) {
			$output->writeln(ConsoleStyle::error("Unknown job class."));

			return Command::INVALID;
		}

		$className = $class->getName();
		$job = new $className;

		if (!($job instanceof Job)) {
			$output->writeln(ConsoleStyle::error("Class is not a Katu job."));

			return Command::INVALID;
		}

		$job->setConsoleInput($input);
		$job->setConsoleOutput($output);

		if ($input->getOption("force")) {
			$job->setIsLockChecked(false);
			$job->setMaxLoadAverage(null);
		}

		$limit = (int)$input->getOption("limit");
		if ($limit) {
			$job->setLimit($limit);
		}

		$args = $input->getOption("args");
		if ($args) {
			$decodedArgs = json_decode($args, true);
			if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedArgs)) {
				$output->writeln(ConsoleStyle::error("Invalid JSON in --args"));

				return Command::INVALID;
			}
			$job->setArgs($decodedArgs);
		}

		try {
			$success = $job->run();
		} catch (\RuntimeException $exception) {
			if (Signals::shouldStop() || $exception->getMessage() === "Interrupted.") {
				$output->writeln(ConsoleStyle::warn("Interrupted — stopping job."));

				return 130;
			}
			throw $exception;
		}

		return $success ? Command::SUCCESS : Command::FAILURE;
	}
}
