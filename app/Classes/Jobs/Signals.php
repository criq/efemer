<?php

namespace App\Classes\Jobs;

/**
 * Immediate stop for CLI jobs on Ctrl+C / SIGINT / SIGTERM / SIGHUP / SIGPIPE,
 * including when run via `docker compose exec -T` (where Docker does not forward
 * SIGINT into the container, but closes the STDIN pipe as soon as the local
 * `docker compose` client exits on Ctrl+C).
 */
class Signals
{
	private static bool $stopRequested = false;
	private static bool $installed = false;

	/** @var resource|null */
	private static $watchdogProc = null;
	private static ?int $watchdogPid = null;

	public static function install(?callable $onHalt = null): void
	{
		if (self::$installed) {
			return;
		}

		self::$installed = true;

		if (function_exists("pcntl_async_signals")) {
			pcntl_async_signals(true);

			$halt = static function (int $signal) use ($onHalt): void {
				self::$stopRequested = true;
				self::stopWatchdog();
				if ($onHalt) {
					try {
						$onHalt($signal);
					} catch (\Throwable) {
					}
				}
				if (function_exists("posix_kill")) {
					@posix_kill((int)getmypid(), 9);
				}
				exit(130);
			};

			pcntl_signal(SIGINT, $halt);
			pcntl_signal(SIGTERM, $halt);
			if (defined("SIGHUP")) {
				pcntl_signal(SIGHUP, $halt);
			}
			if (defined("SIGPIPE")) {
				pcntl_signal(SIGPIPE, $halt);
			}
		}

		self::startStdinDisconnectWatchdog();
		register_shutdown_function([self::class, "stopWatchdog"]);
	}

	/**
	 * When launched from an interactive terminal (with or without `-T`), STDIN
	 * is open and blocks waiting for input. As soon as the user presses Ctrl+C,
	 * the host `docker compose` client exits and closes the STDIN pipe. A tiny
	 * watchdog subprocess watches STDIN for EOF/ETX and SIGKILLs this job even
	 * if PHP is mid-download or mid-GCS upload.
	 */
	private static function startStdinDisconnectWatchdog(): void
	{
		if (!defined("STDIN") || !is_resource(STDIN) || !function_exists("proc_open")) {
			return;
		}

		@stream_set_blocking(STDIN, false);
		$read = [STDIN];
		$write = null;
		$except = null;
		$ready = @stream_select($read, $write, $except, 0, 50000);

		if ($ready !== 0) {
			// STDIN is already readable at startup — check if it is /dev/null or closed.
			$peek = @fread(STDIN, 1);
			if ($peek === "" || $peek === false || @feof(STDIN)) {
				return;
			}
		}

		$parentPid = (int)getmypid();
		$script = sprintf(
			'$parent = %d; stream_set_blocking(STDIN, false); while (true) { if (!posix_kill($parent, 0)) { exit(0); } $r = [STDIN]; $w = null; $e = [STDIN]; $n = @stream_select($r, $w, $e, 0, 100000); if ($n > 0) { $c = @fread(STDIN, 64); if ($c === "" || $c === false || feof(STDIN) || strpos($c, "\x03") !== false) { @posix_kill($parent, 9); exit(0); } } }',
			$parentPid,
		);

		$descriptors = [
			0 => STDIN,
			1 => ["file", "/dev/null", "w"],
			2 => ["file", "/dev/null", "w"],
		];

		$proc = @proc_open(["php", "-r", $script], $descriptors, $pipes);
		if (is_resource($proc)) {
			self::$watchdogProc = $proc;
			$status = @proc_get_status($proc);
			self::$watchdogPid = isset($status["pid"]) ? (int)$status["pid"] : null;
		}
	}

	public static function stopWatchdog(): void
	{
		if (self::$watchdogPid && function_exists("posix_kill")) {
			@posix_kill(self::$watchdogPid, 9);
			self::$watchdogPid = null;
		}
		if (is_resource(self::$watchdogProc)) {
			@proc_terminate(self::$watchdogProc, 9);
			@proc_close(self::$watchdogProc);
			self::$watchdogProc = null;
		}
	}

	public static function requestStop(): void
	{
		self::$stopRequested = true;
	}

	public static function shouldStop(): bool
	{
		return self::$stopRequested;
	}

	public static function throwIfStopped(): void
	{
		if (self::$stopRequested) {
			throw new \RuntimeException("Interrupted.");
		}
	}
}
