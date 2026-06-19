<?php

namespace App\Config;

use Katu\Tools\Routing\Route;
use Katu\Tools\Routing\RouteCollection;

class RouterConfig extends \Katu\Config\RouterConfig
{
	public function getRoutes(): RouteCollection
	{
		return new RouteCollection([
			"admin.index" => new Route("/admin", [\App\Controllers\Admin\Index::class, "getResponse"]),
			"admin.pages.createPageComponent" => new Route("/admin/pages/{pageId}/components/create/{kindCode}", [\App\Controllers\Admin\Pages\CreatePageComponent::class, "getResponse"]),
			"admin.pages.deletePageComponent" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/delete", [\App\Controllers\Admin\Pages\DeletePageComponent::class, "getResponse"]),
			"admin.pages.editPageComponent" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/edit", [\App\Controllers\Admin\Pages\EditPageComponent::class, "getResponse"]),
			"admin.pages.viewPageComponent" => new Route("/admin/pages/{pageId}/components/{pageComponentId}", [\App\Controllers\Admin\Pages\ViewPageComponent::class, "getResponse"]),
			"admin.pages.createPage" => new Route("/admin/pages/create", [\App\Controllers\Admin\Pages\CreatePage::class, "getResponse"]),
			"admin.pages.deletePage" => new Route("/admin/pages/{pageId}/delete", [\App\Controllers\Admin\Pages\DeletePage::class, "getResponse"]),
			"admin.pages.editPage" => new Route("/admin/pages/{pageId}/edit", [\App\Controllers\Admin\Pages\EditPage::class, "getResponse"]),
			"admin.pages.viewPage" => new Route("/admin/pages/{pageId}", [\App\Controllers\Admin\Pages\ViewPage::class, "getResponse"]),
			"admin.pages.index" => new Route("/admin/pages", [\App\Controllers\Admin\Pages\Index::class, "getResponse"]),
			"homepage.index" => new Route("/", [\App\Controllers\Homepage\Index::class, "getResponse"]),
			"images.getVersion" => new Route("/images/{imagePackage}/{versionCode}.{extension}", [\App\Controllers\Images::class, "getVersion"]),
		]);
	}
}
