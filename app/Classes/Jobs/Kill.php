<?php

namespace App\Classes\Jobs;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Kill orphaned jobs:run / jobs:loop PHP processes inside the container.
 *
 * Needed when Ctrl+C was used with `docker compose exec -T` (no TTY), which
 * disconnects the client but leaves PHP running.
 */
class Kill extends Command
{
	public function getName(): string
	{
		return "jobs:kill";
	}

	public function getDescription(): string
	{
		return "Kill running jobs:run / jobs:loop processes in this container";
	}

	protected function configure(): void
	{
		$this->addOption("force", "f", InputOption::VALUE_NONE, "SIGKILL instead of SIGTERM.");
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$output->setDecorated(true);
		$self = getmypid();
		$signal = $input->getOption("force") ? 9 : 15;
		$killed = [];

		foreach ($this->listJobPids() as $pid) {
			if ($pid === $self) {
				continue;
			}
			$cmd = $this->commandLine($pid);
			if (!posix_kill($pid, $signal)) {
				$output->writeln(ConsoleStyle::warn("Could not signal pid {$pid}"));
				continue;
			}
			$killed[] = "{$pid} {$cmd}";
		}

		if ($killed === []) {
			$output->writeln(ConsoleStyle::muted("No running jobs found."));

			return Command::SUCCESS;
		}

		foreach ($killed as $line) {
			$output->writeln(ConsoleStyle::ok("Killed") . " " . ConsoleStyle::path($line));
		}

		return Command::SUCCESS;
	}

	/**
	 * @return list<int>
	 */
	private function listJobPids(): array
	{
		$pids = [];
		foreach (glob("/proc/[0-9]*/cmdline") ?: [] as $cmdlineFile) {
			$raw = @file_get_contents($cmdlineFile);
			if ($raw === false || $raw === "") {
				continue;
			}
			$cmd = str_replace("\0", " ", $raw);
			if (!str_contains($cmd, "bin/cli.php")) {
				continue;
			}
			if (!str_contains($cmd, "jobs:run") && !str_contains($cmd, "jobs:loop")) {
				continue;
			}
			if (str_contains($cmd, "jobs:kill")) {
				continue;
			}
			$pid = (int)basename(dirname($cmdlineFile));
			if ($pid > 1) {
				$pids[] = $pid;
			}
		}

		return $pids;
	}

	private function commandLine(int $pid): string
	{
		$raw = @file_get_contents("/proc/{$pid}/cmdline");
		if ($raw === false || $raw === "") {
			return "(unknown)";
		}

		return trim(str_replace("\0", " ", $raw));
	}
}
