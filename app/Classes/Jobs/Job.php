<?php

namespace App\Classes\Jobs;

use Katu\Tools\Calendar\Time;
use Katu\Tools\Locks\Procedure;
use Katu\Types\TIdentifier;

/**
 * App job base: Procedure swallows throwables, so capture them and fail the run.
 */
abstract class Job extends \Katu\Tools\Jobs\Job
{
	protected ?\Throwable $callbackException = null;

	public function getProcedure(): Procedure
	{
		$originalCallback = $this->getCallback();
		$job = $this;

		$wrappedCallback = function () use ($originalCallback, $job) {
			try {
				return call_user_func($originalCallback);
			} catch (\Throwable $exception) {
				$job->callbackException = $exception;
				throw $exception;
			}
		};

		return (new Procedure($this->getIdentifier(), $this->getTimeout(), $wrappedCallback))
			->setIsLockChecked($this->getIsLockChecked());
	}

	public function run(): bool
	{
		$this->callbackException = null;

		$logger = \App\App::getLogger(new TIdentifier(__CLASS__));
		$loggerContext = [
			"job" => $this->getTitle(),
			"id" => $this->getId(),
			"args" => serialize($this->getArgs()),
		];

		try {
			if (!$this->getProcedure()->getIsExecutable()) {
				$message = "Job {$this->getTitle()} locked.";
				$logger->notice($message, $loggerContext);
				$this->outputLine(ConsoleStyle::jobLocked($this->getTitle()));

				return false;
			}

			$maxLoadAverage = $this->getMaxLoadAverage();
			$loadAverage = \Katu\Tools\System\System::getLoadAveragePerCpu()[0];
			if (
				!self::getIgnoreMaxLoadAverage()
				&& !(new \App\Config\AppConfig)->getIsEnvironment("DEVELOPMENT")
				&& $maxLoadAverage
				&& $loadAverage >= $maxLoadAverage
			) {
				$message = "Load average {$loadAverage} above {$maxLoadAverage}.";
				$logger->notice($message);
				$this->outputLine(ConsoleStyle::warn($message));

				return false;
			}

			$this->setTimeStarted(new Time);
			$plainStart = "Job {$this->getTitle()} started.";
			$logger->info($plainStart, array_merge($loggerContext, ["event" => "job.start"]));
			$this->outputLine(ConsoleStyle::jobStarted($this->getTitle()));

			$this->getProcedure()->run();

			if ($this->callbackException) {
				$this->outputLine(ConsoleStyle::error($this->callbackException->getMessage()));
				$logger->error($this->callbackException);

				return false;
			}

			$this->setTimeFinished(new Time);
			$plainEnd = "Job {$this->getTitle()} finished.";
			$logger->info($plainEnd, array_merge($loggerContext, ["event" => "job.end"]));
			$this->outputLine(ConsoleStyle::jobFinished($this->getTitle()));

			return true;
		} catch (\Throwable $exception) {
			$logger->error($exception);
			$this->outputLine(ConsoleStyle::error($exception->getMessage()));

			return false;
		}
	}
}
