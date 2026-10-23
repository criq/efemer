<?php

namespace App\Classes\Pages;

use App\Classes\Pages\Components\Kinds\ImageGalleryKind;
use App\Classes\Pages\Components\Kinds\ImageKind;
use App\Classes\Pages\Components\Kinds\TextKind;
use App\Classes\Pages\PageGallery\TemplateCollection;
use App\Classes\Tools\Slugger;
use Katu\Files\Upload;
use Katu\Tools\Calendar\Time;

class PostScaffolder
{
	public function create(string $title, Time $date, ?Upload $heroUpload = null): Page
	{
		$title = trim($title);
		if ($title === "") {
			throw new \InvalidArgumentException("Zadejte název příspěvku.");
		}

		$path = $this->uniqueDatedPath($title, $date);

		$page = new Page;
		$page->setTimeCreated($date);
		$page->setTitle($title);
		$page->setPath($path);
		$page->persist();

		$position = 1;

		$titleText = $this->createComponent($page, new TextKind, $position++);
		$titleText->setValue("<h1>" . htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, "UTF-8") . "</h1>")->persist();

		$hero = $this->createComponent($page, new ImageKind, $position++);
		if ($heroUpload) {
			PageComponentStorageFileCollection::replaceWithUpload($hero, $heroUpload);
		}

		$this->createComponent($page, new TextKind, $position++);

		$gallery = $this->createComponent($page, new ImageGalleryKind, $position++);
		$gallery->setTemplateCode("NATURAL");
		$gallery->setGalleryProperties(GalleryProperties::createDefault());
		$gallery->persist();

		$this->prependToHomepageDiary($page);

		return $page;
	}

	public static function unlinkFromHomepageDiary(Page $page): void
	{
		$diary = static::findHomepageDiaryGallery();
		if (!$diary) {
			return;
		}

		$entries = [];
		foreach ($diary->getPageComponentPages() as $link) {
			if ((int)$link->getPage()->getId() === (int)$page->getId()) {
				continue;
			}

			$entries[] = [
				"pageId" => (int)$link->getPage()->getId(),
				"template" => $link->getTemplate()->getCode()->getConstantFormat(),
			];
		}

		PageComponentPageCollection::syncFromPageEntries($diary, $entries);
	}

	public static function findHomepageDiaryGallery(): ?PageComponent
	{
		$homepage = Page::getHomepage();
		if (!$homepage) {
			return null;
		}

		$gallery = null;
		foreach ($homepage->getRootPageComponents() as $component) {
			if ($component->getKind() && $component->getKind()::getCode()->getConstantFormat() === "PAGE_GALLERY") {
				$gallery = $component;
			}
		}

		return $gallery;
	}

	private function uniqueDatedPath(string $title, Time $date): string
	{
		$slugTitle = \URLify::slug($title, 200, "-", "cs");
		if ($slugTitle === "") {
			$slugTitle = "prispevek";
		}

		$base = Slugger::slugifyPagePath($date->format("Y/m/d") . "/" . $slugTitle);
		$path = $base;
		$suffix = 2;

		while (Page::getOneBy(["path" => $path])) {
			$path = $base . "-" . $suffix;
			$suffix++;
		}

		return $path;
	}

	private function prependToHomepageDiary(Page $page): void
	{
		$diary = static::findHomepageDiaryGallery();
		if (!$diary) {
			return;
		}

		$entries = [[
			"pageId" => (int)$page->getId(),
			"template" => TemplateCollection::getDefaultCode(),
		]];

		foreach ($diary->getPageComponentPages() as $link) {
			$entries[] = [
				"pageId" => (int)$link->getPage()->getId(),
				"template" => $link->getTemplate()->getCode()->getConstantFormat(),
			];
		}

		PageComponentPageCollection::syncFromPageEntries($diary, $entries);
	}

	private function createComponent(Page $page, $kind, int $position): PageComponent
	{
		$component = new PageComponent;
		$component->setTimeCreated(new Time);
		$component->setPage($page);
		$component->setParent(null);
		$component->setKind($kind);
		$component->setPosition($position);
		$component->setTemplateCode($kind::getTemplates()->getDefaultCode());
		$component->persist();

		return $component;
	}
}
