<?php

namespace App\Classes\Pages;

class GalleryProperties
{
	public const COLUMNS_MIN = 1;
	public const COLUMNS_MAX = 12;

	public int $columnsMin = 2;
	public int $columnsMax = 4;

	public static function createDefault(): GalleryProperties
	{
		return new static;
	}

	public static function fromJson(?string $json): GalleryProperties
	{
		$properties = static::createDefault();

		if ($json === null || $json === "") {
			return $properties;
		}

		$data = json_decode($json, true);
		if (!is_array($data)) {
			return $properties;
		}

		return static::fromArray($data);
	}

	public static function fromArray(array $data): GalleryProperties
	{
		$properties = static::createDefault();
		$properties->columnsMin = static::normalizeColumnCount($data["columnsMin"] ?? $properties->columnsMin);
		$properties->columnsMax = static::normalizeColumnCount($data["columnsMax"] ?? $properties->columnsMax);

		if ($properties->columnsMax < $properties->columnsMin) {
			$properties->columnsMax = $properties->columnsMin;
		}

		return $properties;
	}

	public function toJson(): string
	{
		return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
	}

	public function toArray(): array
	{
		return [
			"columnsMin" => $this->columnsMin,
			"columnsMax" => $this->columnsMax,
		];
	}

	public function getStyleAttribute(): string
	{
		return sprintf(
			"--gallery-cols-min: %d; --gallery-cols-max: %d;",
			$this->columnsMin,
			$this->columnsMax,
		);
	}

	private static function normalizeColumnCount(mixed $value): int
	{
		$count = (int)$value;

		return max(static::COLUMNS_MIN, min(static::COLUMNS_MAX, $count));
	}
}
