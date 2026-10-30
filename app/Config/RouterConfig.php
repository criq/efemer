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
			"admin.pages.updatePageComponentSettings" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/settings", [\App\Controllers\Admin\Pages\UpdatePageComponentSettings::class, "getResponse"]),
			"admin.pages.uploadStorageFiles" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/storage-files", [\App\Controllers\Admin\Pages\UploadStorageFiles::class, "getResponse"]),
			"admin.pages.reorderStorageFiles" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/storage-files/reorder", [\App\Controllers\Admin\Pages\ReorderStorageFiles::class, "getResponse"]),
			"admin.pages.updateStorageFile" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/storage-files/{storageFileId}", [\App\Controllers\Admin\Pages\UpdateStorageFile::class, "getResponse"]),
			"admin.pages.deleteStorageFile" => new Route("/admin/pages/{pageId}/components/{pageComponentId}/storage-files/{storageFileId}/delete", [\App\Controllers\Admin\Pages\DeleteStorageFile::class, "getResponse"]),
			"admin.pages.moveStorageFile" => new Route("/admin/pages/{pageId}/storage-files/{storageFileId}/move", [\App\Controllers\Admin\Pages\MoveStorageFile::class, "getResponse"]),
			"admin.pages.viewPageComponent" => new Route("/admin/pages/{pageId}/components/{pageComponentId}", [\App\Controllers\Admin\Pages\ViewPageComponent::class, "getResponse"]),
			"admin.pages.builder.value" => new Route("/admin/pages/{pageId}/builder/components/{pageComponentId}/value", [\App\Controllers\Admin\Pages\Builder\UpdateValue::class, "getResponse"]),
			"admin.pages.builder.pageLinks" => new Route("/admin/pages/{pageId}/builder/components/{pageComponentId}/page-links", [\App\Controllers\Admin\Pages\Builder\UpdatePageLinks::class, "getResponse"]),
			"admin.pages.builder.delete" => new Route("/admin/pages/{pageId}/builder/components/{pageComponentId}/delete", [\App\Controllers\Admin\Pages\Builder\DeleteComponent::class, "getResponse"]),
			"admin.pages.builder.fragment" => new Route("/admin/pages/{pageId}/builder/components/{pageComponentId}/fragment", [\App\Controllers\Admin\Pages\Builder\Fragment::class, "getResponse"]),
			"admin.pages.builder.create" => new Route("/admin/pages/{pageId}/builder/components", [\App\Controllers\Admin\Pages\Builder\CreateComponent::class, "getResponse"]),
			"admin.pages.builder.tree" => new Route("/admin/pages/{pageId}/builder/tree", [\App\Controllers\Admin\Pages\Builder\UpdateTree::class, "getResponse"]),
			"admin.pages.builder" => new Route("/admin/pages/{pageId}/builder", [\App\Controllers\Admin\Pages\Builder::class, "getResponse"]),
			"admin.pages.createPost" => new Route("/admin/pages/create-post", [\App\Controllers\Admin\Pages\CreatePost::class, "getResponse"]),
			"admin.pages.createPage" => new Route("/admin/pages/create", [\App\Controllers\Admin\Pages\CreatePage::class, "getResponse"]),
			"admin.pages.deletePage" => new Route("/admin/pages/{pageId}/delete", [\App\Controllers\Admin\Pages\DeletePage::class, "getResponse"]),
			"admin.pages.editPage" => new Route("/admin/pages/{pageId}/edit", [\App\Controllers\Admin\Pages\EditPage::class, "getResponse"]),
			"admin.pages.viewPage" => new Route("/admin/pages/{pageId}", [\App\Controllers\Admin\Pages\ViewPage::class, "getResponse"]),
			"admin.pages.index" => new Route("/admin/pages", [\App\Controllers\Admin\Pages\Index::class, "getResponse"]),
			"admin.storageFiles.index" => new Route("/admin/storage-files", [\App\Controllers\Admin\StorageFiles\Index::class, "getResponse"]),
			"admin.posts.edit" => new Route("/admin/posts/{postId}", [\App\Controllers\Admin\Posts\Edit::class, "getResponse"]),
			"admin.posts.index" => new Route("/admin/posts", [\App\Controllers\Admin\Posts\Index::class, "getResponse"]),
			"admin.ingest.view" => new Route("/admin/ingest/{ingestItemId}", [\App\Controllers\Admin\Ingest\View::class, "getResponse"]),
			"admin.ingest.index" => new Route("/admin/ingest", [\App\Controllers\Admin\Ingest\Index::class, "getResponse"]),
			"admin.events.create" => new Route("/admin/events/create", [\App\Controllers\Admin\Events\Edit::class, "getResponse"]),

			"admin.events.delete" => new Route("/admin/events/{eventId}/delete", [\App\Controllers\Admin\Events\Delete::class, "getResponse"]),
			"admin.events.edit" => new Route("/admin/events/{eventId}", [\App\Controllers\Admin\Events\Edit::class, "getResponse"]),
			"admin.events.index" => new Route("/admin/events", [\App\Controllers\Admin\Events\Index::class, "getResponse"]),
			"homepage.index" => new Route("/", [\App\Controllers\Homepage\Index::class, "getResponse"]),
			"site.kontakt" => new Route("/kontakt", [\App\Controllers\Site\Kontakt::class, "getResponse"]),
			"site.vTisku" => new Route("/v-tisku", [\App\Controllers\Site\VTisku::class, "getResponse"]),
			"site.newsletter" => new Route("/newsletter", [\App\Controllers\Site\Newsletter::class, "getResponse"]),
			"sections.ritualy" => new Route("/svatebni-ritualy", [\App\Controllers\Sections\Index::class, "ritualy"]),
			"sections.umeni" => new Route("/umeni", [\App\Controllers\Sections\Index::class, "umeni"]),
			"sections.denik" => new Route("/denik", [\App\Controllers\Sections\Index::class, "denik"]),
			"sections.udalosti" => new Route("/udalosti", [\App\Controllers\Events\Index::class, "getResponse"]),
			"posts.ritualy" => new Route("/svatebni-ritualy/{slug:.+}", [\App\Controllers\Posts\View::class, "ritualy"]),
			"posts.umeni" => new Route("/umeni/{slug:.+}", [\App\Controllers\Posts\View::class, "umeni"]),
			"posts.denik" => new Route("/denik/{slug:.+}", [\App\Controllers\Posts\View::class, "denik"]),
			"events.view" => new Route("/udalosti/{slug:.+}", [\App\Controllers\Events\View::class, "getResponse"]),
			// Legacy journal URLs → /denik
			"legacy.himmelhaus" => new Route("/himmelhaus", [\App\Controllers\Site\Redirect::class, "toDenik"]),
			"legacy.workshopy" => new Route("/workshopy", [\App\Controllers\Site\Redirect::class, "toDenik"]),
			"legacy.himmelhaus.post" => new Route("/himmelhaus/{slug:.+}", [\App\Controllers\Site\Redirect::class, "toDenikPost"]),
			"legacy.workshopy.post" => new Route("/workshopy/{slug:.+}", [\App\Controllers\Site\Redirect::class, "toDenikPost"]),
			"images.getVersion" => new Route("/images/{imagePackage}/{versionCode}.{extension}", [\App\Controllers\Images::class, "getVersion"]),
			"pages.view" => new Route("/{path:.+}", [\App\Controllers\Pages\View::class, "getByPath"]),
		]);
	}
}
