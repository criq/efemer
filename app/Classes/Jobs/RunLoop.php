<?php

namespace App\Classes\Jobs;

use Katu\Tools\Jobs\Job;
use Katu\Types\TClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunLoop extends Command
{
	public function getName(): string
	{
		return "jobs:loop";
	}

	public function getDescription(): string
	{
		return "Run a job repeatedly on an interval";
	}

	protected function configure(): void
	{
		$this->addArgument("class", InputArgument::REQUIRED, "Job class portable name (backslashes as dots).");
		$this->addOption("interval", "i", InputOption::VALUE_REQUIRED, "Seconds between runs.", "21600");
		$this->addOption("force", "f", InputOption::VALUE_NONE, "Bypass job locks / load gate each run.");
		$this->addOption("limit", "l", InputOption::VALUE_REQUIRED, "Limit items per run.");
		$this->addOption("args", null, InputOption::VALUE_OPTIONAL, "JSON object of job arguments.");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->setDecorated(true);
		Job::setIgnoreMaxLoadAverage(true);

		$class = TClass::createFromPortableName($input->getArgument("class"));
		if (!$class->exists()) {
			$output->writeln(ConsoleStyle::error("Unknown job class."));

			return Command::INVALID;
		}

		$className = $class->getName();
		$interval = max(60, (int)$input->getOption("interval"));
		$stop = false;

		if (function_exists("pcntl_async_signals")) {
			pcntl_async_signals(true);
			$halt = static function () use (&$stop): void {
				$stop = true;
			};
			pcntl_signal(SIGTERM, $halt);
			pcntl_signal(SIGINT, $halt);
		}

		while (!$stop) {
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

			$job->run();

			if ($stop) {
				break;
			}

			$output->writeln(ConsoleStyle::muted(date("c") . " Sleeping {$interval}s until the next run."));
			$slept = 0;
			while ($slept < $interval && !$stop) {
				sleep(min(30, $interval - $slept));
				$slept += 30;
			}
		}

		return Command::SUCCESS;
	}
}
