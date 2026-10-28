<?php

namespace App\Classes\Jobs\Ingest;

use App\Classes\Ingest\EvalSet;
use App\Classes\Jobs\ConsoleStyle;
use App\Classes\Jobs\Job;
use Katu\Tools\Calendar\Timeout;

/**
 * Score KeywordIngest against golden cover/role eval sets.
 *
 * ./bin/job jobs:run App.Classes.Jobs.Ingest.EvaluateKeywords -f
 */
class EvaluateKeywords extends Job
{
	const DEFAULT_INTERVAL = "1 day";
	const DEFAULT_TIMEOUT = "10 minutes";

	public function __construct(array $args = [])
	{
		parent::__construct($args);
		$this->setTimeout(new Timeout(static::DEFAULT_TIMEOUT));
		$this->setMaxLoadAverage(null);
	}

	public function getCallback(): callable
	{
		return function () {
			$result = EvalSet::evaluate();
			$cover = $result["cover"];
			$role = $result["role"];

			$this->outputLine(ConsoleStyle::step("Cover eval"));
			$this->outputLine(sprintf(
				"  scored %s/%s · pass %s · fail %s · missing %s · accuracy %s",
				ConsoleStyle::count($cover["scored"]),
				ConsoleStyle::count($cover["total"]),
				ConsoleStyle::count($cover["pass"]),
				ConsoleStyle::count($cover["fail"]),
				ConsoleStyle::count($cover["missing"]),
				$this->pct($cover["pass"], $cover["scored"]),
			));

			$this->outputLine(ConsoleStyle::step("Role eval (expected leads present)"));
			$this->outputLine(sprintf(
				"  scored %s/%s · pass %s · fail %s · missing %s · accuracy %s",
				ConsoleStyle::count($role["scored"]),
				ConsoleStyle::count($role["total"]),
				ConsoleStyle::count($role["pass"]),
				ConsoleStyle::count($role["fail"]),
				ConsoleStyle::count($role["missing"]),
				$this->pct($role["pass"], $role["scored"]),
			));
			$this->outputLine(sprintf(
				"  pattern pass %s · fail %s",
				ConsoleStyle::count($role["patternPass"]),
				ConsoleStyle::count($role["patternFail"]),
			));

			$failures = array_merge($cover["failures"], $role["failures"]);
			if ($failures) {
				$this->outputLine(ConsoleStyle::warn("Failures (" . count($failures) . "):"));
				foreach (array_slice($failures, 0, 40) as $line) {
					$this->outputLine("  " . ConsoleStyle::muted($line));
				}
				if (count($failures) > 40) {
					$this->outputLine(ConsoleStyle::muted("  … " . (count($failures) - 40) . " more"));
				}
			} else {
				$this->outputLine(ConsoleStyle::ok("All scored eval items passed."));
			}
		};
	}

	private function pct(int $pass, int $scored): string
	{
		if ($scored <= 0) {
			return "n/a";
		}

		return round(100 * $pass / $scored, 1) . "%";
	}
}
