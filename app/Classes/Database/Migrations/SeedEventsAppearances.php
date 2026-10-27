<?php

namespace App\Classes\Database\Migrations;

use Katu\PDO\Connection;

class SeedEventsAppearances implements MigrationInterface
{
	public function getName(): string
	{
		return "seed_events_appearances";
	}

	public function up(Connection $connection): void
	{
		$now = date("Y-m-d H:i:s");

		foreach ($this->events() as $event) {
			$existing = $connection->createQuery("
				SELECT id FROM events WHERE title = :title AND startsAt = :startsAt
			", [
				"title" => $event["title"],
				"startsAt" => $event["startsAt"],
			])->getResult()->getOne();

			if ($existing) {
				continue;
			}

			$connection->createQuery("
				INSERT INTO events (timeCreated, title, startsAt, endsAt, place, bodyHtml, section, published)
				VALUES (:timeCreated, :title, :startsAt, :endsAt, :place, :bodyHtml, :section, 1)
			", array_merge($event, ["timeCreated" => $now]))->getResult();
		}

		foreach ($this->appearances() as $appearance) {
			$existing = $connection->createQuery("
				SELECT id FROM appearances WHERE url = :url
			", [
				"url" => $appearance["url"],
			])->getResult()->getOne();

			if ($existing) {
				continue;
			}

			$connection->createQuery("
				INSERT INTO appearances (timeCreated, title, outlet, url, publishedAt)
				VALUES (:timeCreated, :title, :outlet, :url, :publishedAt)
			", array_merge($appearance, ["timeCreated" => $now]))->getResult();
		}
	}

	private function events(): array
	{
		return [
			[
				"title" => "Kupalo",
				"startsAt" => "2026-06-25 18:00:00",
				"endsAt" => "2026-06-25 23:00:00",
				"place" => "Brno",
				"bodyHtml" => "<p>Slavnost letního slunovratu se zpěvačkou Barborou Sárou.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Alba et Aqua",
				"startsAt" => "2026-06-27 10:00:00",
				"endsAt" => "2026-06-27 20:00:00",
				"place" => "Himmelhaus",
				"bodyHtml" => "<p>Rituální oděv s Natálií Faltýnovou z Košilela. Byliny, oltáře, koupel, oheň.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Kořeny se hledají v zemi",
				"startsAt" => "2026-07-17 10:00:00",
				"endsAt" => "2026-07-19 18:00:00",
				"place" => "Löblhof",
				"bodyHtml" => "<p>Výprava do zaniklých německých osad s Marií Krupičkovou a hostina z rozvalinových rybízů.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Prázdniny s Marcelem",
				"startsAt" => "2026-08-08 10:00:00",
				"endsAt" => "2026-08-09 18:00:00",
				"place" => "Himmelhaus",
				"bodyHtml" => "<p>Literárně-tvůrčí pobyt s Janou Švecovou na počest Marcela Pagnola.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Nanebevzetí Panny Marie",
				"startsAt" => "2026-08-14 10:00:00",
				"endsAt" => "2026-08-16 18:00:00",
				"place" => "Himmelhaus",
				"bodyHtml" => "<p>Krajina skrze tělesnou zkušenost s Klárou Vukmanov-Šimokov.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Patience Gray",
				"startsAt" => "2026-08-22 10:00:00",
				"endsAt" => "2026-08-23 18:00:00",
				"place" => "Himmelhaus",
				"bodyHtml" => "<p>Vaření a hodování na počest Patience Gray a Normana Mommense.</p>",
				"section" => "himmelhaus",
			],
			[
				"title" => "Wanderlust",
				"startsAt" => "2026-11-01 10:00:00",
				"endsAt" => null,
				"place" => "České středohoří",
				"bodyHtml" => "<p>Výroční pouť k magnetovci s Janem Löblem.</p>",
				"section" => "himmelhaus",
			],
		];
	}

	private function appearances(): array
	{
		return [
			[
				"title" => "Cítit krajinu a duch místa",
				"outlet" => "NILA",
				"url" => "https://www.nila.cz/blog/archiv/navstevy/citit-krajinu-a-duch-mista/",
				"publishedAt" => "2026-06-15",
			],
			[
				"title" => "Na lednovou svatbu nechtějte pivoňky",
				"outlet" => "Český rozhlas Plus",
				"url" => "https://plus.rozhlas.cz/na-lednovou-svatbu-nechtejte-pivonky-mini-konceptualni-zahradnice-ve-svem-9174613",
				"publishedAt" => "2024-01-01",
			],
			[
				"title" => "Svět je zahlcován stále novými výrobky",
				"outlet" => "GrapesMag",
				"url" => "https://grapesmag.cz/interview/rozhovor-efemer/",
				"publishedAt" => "2023-01-01",
			],
			[
				"title" => "O tradicích a rostlinách",
				"outlet" => "iDNES Mikrosvěty",
				"url" => "https://www.idnes.cz/podcasty/mikrosvety/lucie-kralikova-efemer-rozhovor-mikrosvety-podcast-tradice.V221116_115527_idnestv_pest",
				"publishedAt" => "2022-11-20",
			],
			[
				"title" => "Chodit pěšky a zajímat se o krajinu",
				"outlet" => "Český rozhlas Vltava",
				"url" => "https://vltava.rozhlas.cz/chodit-pesky-a-zajimat-se-o-krajinu-lucie-kralikova-skrz-rostliny-a-ritualy-8268468",
				"publishedAt" => "2020-08-07",
			],
			[
				"title" => "Svátosti pro každého",
				"outlet" => "Forbes",
				"url" => "https://forbes.cz/svatosti-pro-kazdeho-vychazi-vizualni-kniha-o-ceskych-tradicich/",
				"publishedAt" => "2020-01-01",
			],
			[
				"title" => "Efemér Lucie Králíkové",
				"outlet" => "ČT art",
				"url" => "https://art.ceskatelevize.cz/profil/efemer-lucie-kralikove-SkZzw",
				"publishedAt" => "2018-01-01",
			],
			[
				"title" => "Spálené květy, černé kořeny i fraktály bodláku",
				"outlet" => "Radio Wave",
				"url" => "https://wave.rozhlas.cz/spalene-kvety-cerne-koreny-i-fraktaly-bodlaku-moderni-svatebni-kytice-je-vic-8085099",
				"publishedAt" => "2019-10-04",
			],
		];
	}
}
