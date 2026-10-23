import Sortable from "sortablejs";
import tinymce from "tinymce/tinymce";
import "tinymce/icons/default";
import "tinymce/themes/silver";
import "tinymce/models/dom";
import "tinymce/plugins/lists";
import "tinymce/plugins/link";
import "tinymce/skins/ui/oxide/skin.js";
import "tinymce/skins/ui/oxide/content.inline.js";
import "tinymce/skins/content/default/content.js";

window.tinymce = tinymce;

const MAX_IMAGE_EDGE = 2400;
const JPEG_QUALITY = 0.85;

const root = document.querySelector("[data-builder]");
if (!root) {
	// Not on builder page.
} else {
	initBuilder(root);
}

function initBuilder(chrome) {
	const canvas = document.querySelector("[data-builder-canvas]");
	const statusEl = chrome.querySelector("[data-builder-status]");
	const token = chrome.dataset.token;
	const createUrl = chrome.dataset.createUrl;
	const treeUrl = chrome.dataset.treeUrl;
	const settings = chrome.querySelector("[data-builder-settings]");
	const addDrawer = chrome.querySelector("[data-builder-add]");
	const pagesDrawer = chrome.querySelector("[data-builder-pages]");
	const pagesList = chrome.querySelector("[data-builder-pages-list]");
	const pagesAddSelect = chrome.querySelector("[data-builder-pages-add]");
	const layoutDrawer = chrome.querySelector("[data-builder-layout]");
	const columnsMinInput = chrome.querySelector("[data-builder-columns-min]");
	const columnsMaxInput = chrome.querySelector("[data-builder-columns-max]");
	const candidatePages = JSON.parse(chrome.dataset.candidatePages || "[]");
	const pageGalleryTemplates = JSON.parse(chrome.dataset.pageGalleryTemplates || "[]");
	let selected = null;
	let activeEditor = null;
	let pendingSaves = 0;
	let sortables = [];
	let pagesEditingBlock = null;
	let layoutEditingBlock = null;

	const setStatus = (text, isError = false) => {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = text;
		statusEl.classList.toggle("is-error", isError);
	};

	const beginSave = () => {
		pendingSaves += 1;
		setStatus("Ukládám…");
	};

	const endSave = (ok = true) => {
		pendingSaves = Math.max(0, pendingSaves - 1);
		if (pendingSaves === 0) {
			setStatus(ok ? "Uloženo" : "Nepodařilo se uložit — zkusit znovu", !ok);
		}
	};

	window.addEventListener("beforeunload", (event) => {
		if (pendingSaves > 0 || (activeEditor && activeEditor.isDirty && activeEditor.isDirty())) {
			event.preventDefault();
			event.returnValue = "";
		}
	});

	const closeDrawers = () => {
		settings.hidden = true;
		addDrawer.hidden = true;
		if (pagesDrawer) {
			pagesDrawer.hidden = true;
		}
		if (layoutDrawer) {
			layoutDrawer.hidden = true;
		}
	};

	const selectBlock = (block) => {
		if (selected === block) {
			return;
		}
		deselect();
		selected = block;
		block.classList.add("is-selected");
	};

	const deselect = async () => {
		if (activeEditor) {
			await saveTextEditor();
		}
		if (selected) {
			selected.classList.remove("is-selected");
		}
		selected = null;
		pagesEditingBlock = null;
		if (pagesDrawer) {
			pagesDrawer.hidden = true;
		}
	};

	const saveTextEditor = async () => {
		if (!activeEditor) {
			return;
		}
		const block = activeEditor.targetElm?.closest?.("[data-builder-block]") || selected;
		const url = block?.dataset?.valueUrl;
		const html = activeEditor.getContent();
		const editor = activeEditor;
		activeEditor = null;

		if (typeof tinymce !== "undefined") {
			tinymce.remove(editor);
		}

		if (!url) {
			return;
		}

		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("value", html);
			const response = await fetch(url, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("save failed");
			}
			const textRoot = block.querySelector("[data-builder-text]");
			if (textRoot) {
				textRoot.innerHTML = html;
			}
			endSave(true);
		} catch (error) {
			endSave(false);
		}
	};

	const openTextEditor = (block) => {
		if (typeof tinymce === "undefined") {
			return;
		}
		const target = block.querySelector("[data-builder-text]");
		if (!target || activeEditor) {
			return;
		}

		const toolbar = block.querySelector("[data-builder-text-toolbar]");

		tinymce.init({
			target,
			inline: true,
			menubar: false,
			skin: false,
			content_css: false,
			license_key: "gpl",
			plugins: "lists link",
			toolbar: "undo redo | blocks | bold italic underline strikethrough | link | bullist numlist | removeformat",
			block_formats: "Odstavec=p; Nadpis 1=h1; Nadpis 2=h2; Nadpis 3=h3",
			fixed_toolbar_container_target: toolbar || undefined,
			toolbar_persist: true,
			valid_elements: "p,br,strong/b,em/i,u,s,a[href|title|target|rel],ul,ol,li,h1[id],h2[id],h3[id],h4[id],blockquote,hr",
			paste_as_text: false,
			setup: (editor) => {
				activeEditor = editor;
				editor.on("init", () => editor.focus());
				let timer = null;
				editor.on("change keyup", () => {
					clearTimeout(timer);
					timer = setTimeout(() => {
						persistText(block, editor.getContent());
					}, 800);
				});
				editor.on("blur", () => {
					clearTimeout(timer);
					saveTextEditor();
				});
			},
		});
	};

	const persistText = async (block, html) => {
		const url = block.dataset.valueUrl;
		if (!url) {
			return;
		}
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("value", html);
			const response = await fetch(url, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("save failed");
			}
			endSave(true);
		} catch (error) {
			endSave(false);
		}
	};

	const downscaleImage = (file) => new Promise((resolve) => {
		if (!file.type.startsWith("image/") || file.type === "image/svg+xml") {
			resolve(file);
			return;
		}

		const url = URL.createObjectURL(file);
		const image = new Image();
		image.onload = () => {
			URL.revokeObjectURL(url);
			const max = Math.max(image.width, image.height);
			if (max <= MAX_IMAGE_EDGE) {
				resolve(file);
				return;
			}
			const scale = MAX_IMAGE_EDGE / max;
			const canvasEl = document.createElement("canvas");
			canvasEl.width = Math.round(image.width * scale);
			canvasEl.height = Math.round(image.height * scale);
			const ctx = canvasEl.getContext("2d");
			ctx.drawImage(image, 0, 0, canvasEl.width, canvasEl.height);
			canvasEl.toBlob((blob) => {
				if (!blob) {
					resolve(file);
					return;
				}
				const name = file.name.replace(/\.\w+$/, "") + ".jpg";
				resolve(new File([blob], name, { type: "image/jpeg" }));
			}, "image/jpeg", JPEG_QUALITY);
		};
		image.onerror = () => {
			URL.revokeObjectURL(url);
			resolve(file);
		};
		image.src = url;
	});

	const refreshBlock = async (block) => {
		const url = block.dataset.fragmentUrl;
		if (!url) {
			return;
		}
		const response = await fetch(url, { credentials: "same-origin" });
		if (!response.ok) {
			throw new Error("fragment failed");
		}
		const data = await response.json();
		const wrapper = document.createElement("div");
		wrapper.innerHTML = data.html;
		const next = wrapper.firstElementChild;
		if (!next) {
			return;
		}
		const section = block.closest(".page-component");
		block.replaceWith(next);
		if (selected && selected.dataset.componentId === next.dataset.componentId) {
			selected = next;
			next.classList.add("is-selected");
		}
		bindBlock(next);
		initSortables();
		renderInsertPoints();
		return next;
	};

	const setUploadProgress = (block, text) => {
		const el = block.querySelector("[data-builder-upload-progress]");
		if (!el) {
			return;
		}
		if (!text) {
			el.hidden = true;
			el.textContent = "";
			return;
		}
		el.hidden = false;
		el.textContent = text;
	};

	const uploadFiles = async (block, files) => {
		const uploadUrl = block.dataset.uploadUrl;
		if (!uploadUrl || !files.length) {
			return;
		}
		beginSave();
		setUploadProgress(block, `Nahrávám 0 / ${files.length}…`);
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			let preparedCount = 0;
			for (const file of files) {
				const prepared = await downscaleImage(file);
				preparedCount += 1;
				setUploadProgress(block, `Připravuji ${preparedCount} / ${files.length}…`);
				formData.append("files[]", prepared, prepared.name);
			}
			setUploadProgress(block, `Odesílám ${files.length}…`);
			const response = await fetch(uploadUrl, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("upload failed");
			}
			await refreshBlock(block);
			setUploadProgress(block, "");
			endSave(true);
		} catch (error) {
			setUploadProgress(block, "Nahrání se nepovedlo");
			endSave(false);
		}
	};

	const deleteBlock = async (block) => {
		const kind = block.dataset.kind;
		const labels = {
			TEXT: "tento text",
			IMAGE: "tuto fotku",
			IMAGE_GALLERY: "tuto galerii fotek",
			PAGE_GALLERY: "tuto galerii stránek",
			COLUMNS: "tyto sloupce",
		};
		const label = labels[kind] || "tuto součást";
		if (!confirm(`Opravdu smazat ${label}?`)) {
			return;
		}
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			const response = await fetch(block.dataset.deleteUrl, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("delete failed");
			}
			const section = block.closest(".page-component");
			if (section && section.parentElement?.hasAttribute("data-builder-root")) {
				section.remove();
			} else {
				const columnItem = block.closest("[data-builder-column-item]");
				(columnItem || block).remove();
			}
			if (selected === block) {
				selected = null;
			}
			endSave(true);
			maybeShowEmpty();
			renderInsertPoints();
		} catch (error) {
			endSave(false);
		}
	};

	const maybeShowEmpty = () => {
		const rootList = document.querySelector("[data-builder-root]");
		if (!rootList) {
			return;
		}
		if (!rootList.querySelector("[data-builder-block]")) {
			if (!rootList.querySelector("[data-builder-empty]")) {
				const empty = document.createElement("p");
				empty.className = "builder-empty";
				empty.dataset.builderEmpty = "";
				empty.textContent = "Zatím tu nic není — přidejte text nebo fotku.";
				rootList.appendChild(empty);
			}
		} else {
			rootList.querySelector("[data-builder-empty]")?.remove();
		}
	};

	const collectTree = () => {
		const items = [];
		const walk = (container, parentId) => {
			const blocks = [...container.children].flatMap((child) => {
				if (child.matches?.("[data-builder-block]")) {
					return [child];
				}
				if (child.matches?.(".page-component")) {
					return [...child.querySelectorAll(":scope > [data-builder-block]")];
				}
				if (child.matches?.("[data-builder-column-item]")) {
					return [...child.querySelectorAll(":scope > [data-builder-block]")];
				}
				return [];
			});

			blocks.forEach((block) => {
				const id = Number(block.dataset.componentId);
				items.push({ id, parentId });
				if (block.dataset.kind === "COLUMNS") {
					const columns = block.querySelector("[data-builder-columns]");
					if (columns) {
						[...columns.querySelectorAll(":scope > [data-builder-column-item] > [data-builder-block]")].forEach((childBlock) => {
							items.push({ id: Number(childBlock.dataset.componentId), parentId: id });
							// Nested columns not allowed; skip deeper.
						});
					}
				}
			});
		};

		const rootList = document.querySelector("[data-builder-root]");
		walk(rootList, null);

		// Include any blocks not captured (safety): dump all blocks with DOM parent.
		const all = [...document.querySelectorAll("[data-builder-block]")];
		if (items.length !== all.length) {
			return all.map((block) => ({
				id: Number(block.dataset.componentId),
				parentId: block.dataset.parentId ? Number(block.dataset.parentId) : null,
			}));
		}

		return items;
	};

	const persistTree = async () => {
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("items", JSON.stringify(collectTree()));
			const response = await fetch(treeUrl, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				const payload = await response.json().catch(() => null);
				const message = payload?.errors?.[0]?.message || "Nepodařilo se uložit pořadí.";
				throw new Error(message);
			}
			// Refresh parentId attributes from DOM structure.
			document.querySelectorAll("[data-builder-block]").forEach((block) => {
				const column = block.closest("[data-builder-columns]");
				const parentBlock = column?.closest("[data-builder-block]");
				block.dataset.parentId = parentBlock ? parentBlock.dataset.componentId : "";
			});
			endSave(true);
		} catch (error) {
			endSave(false);
			alert(error.message || "Nepodařilo se uložit pořadí.");
		}
	};

	const createBlock = async (kind, parentId = null, afterId = null) => {
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("kind", kind);
			if (parentId) {
				formData.append("parentId", String(parentId));
			}
			if (afterId) {
				formData.append("afterId", String(afterId));
			}
			const response = await fetch(createUrl, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("create failed");
			}
			const data = await response.json();
			const wrapper = document.createElement("div");
			wrapper.innerHTML = data.html;
			const block = wrapper.firstElementChild;
			const rootList = document.querySelector("[data-builder-root]");
			rootList.querySelector("[data-builder-empty]")?.remove();

			if (parentId) {
				const parent = document.querySelector(`[data-builder-block][data-component-id="${parentId}"]`);
				const columns = parent?.querySelector("[data-builder-columns]");
				if (columns) {
					columns.querySelector("[data-builder-drop-hint]")?.remove();
					const item = document.createElement("div");
					item.className = "page-component-columns__item";
					item.dataset.builderColumnItem = "";
					item.appendChild(block);
					columns.appendChild(item);
				}
			} else {
				const section = document.createElement("section");
				section.className = "page-component";
				section.appendChild(block);
				if (afterId) {
					const after = document.querySelector(`[data-builder-block][data-component-id="${afterId}"]`);
					const afterSection = after?.closest(".page-component");
					afterSection?.after(section);
				} else {
					rootList.appendChild(section);
				}
			}

			bindBlock(block);
			initSortables();
			renderInsertPoints();
			selectBlock(block);
			if (kind === "TEXT") {
				openTextEditor(block);
			}
			endSave(true);
			addDrawer.hidden = true;
			delete addDrawer.dataset.insertAfterId;
		} catch (error) {
			endSave(false);
		}
	};

	const readLinkedPages = (block) => {
		try {
			return JSON.parse(block.dataset.linkedPages || "[]");
		} catch (error) {
			return [];
		}
	};

	const renderPagesDrawer = (block) => {
		if (!pagesDrawer || !pagesList || !pagesAddSelect) {
			return;
		}

		const linked = readLinkedPages(block);
		pagesList.innerHTML = "";

		if (!linked.length) {
			const empty = document.createElement("p");
			empty.className = "builder-drawer__hint";
			empty.textContent = "Zatím žádné odkazy.";
			pagesList.appendChild(empty);
		}

		linked.forEach((entry, index) => {
			const row = document.createElement("div");
			row.className = "builder-pages-row";

			const title = document.createElement("div");
			title.className = "builder-pages-row__title";
			title.textContent = entry.title || `Stránka ${entry.pageId}`;

			const path = document.createElement("div");
			path.className = "builder-pages-row__path";
			path.textContent = entry.path || "";

			const template = document.createElement("select");
			template.className = "builder-pages-row__template";
			pageGalleryTemplates.forEach((option) => {
				const opt = document.createElement("option");
				opt.value = option.value;
				opt.textContent = option.label;
				if (option.value === entry.template) {
					opt.selected = true;
				}
				template.appendChild(opt);
			});
			template.addEventListener("change", () => {
				linked[index].template = template.value;
				block.dataset.linkedPages = JSON.stringify(linked);
				persistPageLinks(block, linked);
			});

			const remove = document.createElement("button");
			remove.type = "button";
			remove.className = "builder-pages-row__remove";
			remove.textContent = "Odebrat";
			remove.addEventListener("click", () => {
				const next = linked.filter((_, i) => i !== index);
				block.dataset.linkedPages = JSON.stringify(next);
				persistPageLinks(block, next).then(() => renderPagesDrawer(block));
			});

			row.appendChild(title);
			row.appendChild(path);
			row.appendChild(template);
			row.appendChild(remove);
			pagesList.appendChild(row);
		});

		const linkedIds = new Set(linked.map((entry) => Number(entry.pageId)));
		pagesAddSelect.innerHTML = '<option value="">— vyberte stránku —</option>';
		candidatePages.forEach((page) => {
			if (linkedIds.has(Number(page.pageId))) {
				return;
			}
			const opt = document.createElement("option");
			opt.value = String(page.pageId);
			opt.textContent = `${page.title} (${page.path})`;
			pagesAddSelect.appendChild(opt);
		});
	};

	const persistPageLinks = async (block, entries) => {
		const url = block.dataset.pageLinksUrl;
		if (!url) {
			return;
		}
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("entries", JSON.stringify(entries));
			const response = await fetch(url, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("pages failed");
			}
			const data = await response.json();
			block.dataset.linkedPages = JSON.stringify(data.entries || entries);
			await refreshBlock(block);
			endSave(true);
		} catch (error) {
			endSave(false);
		}
	};

	const openPagesDrawer = (block) => {
		pagesEditingBlock = block;
		closeDrawers();
		selectBlock(block);
		renderPagesDrawer(block);
		pagesDrawer.hidden = false;
	};

	const openLayoutDrawer = (block) => {
		layoutEditingBlock = block;
		closeDrawers();
		selectBlock(block);
		if (columnsMinInput) {
			columnsMinInput.value = block.dataset.columnsMin || "2";
		}
		if (columnsMaxInput) {
			columnsMaxInput.value = block.dataset.columnsMax || "4";
		}
		layoutDrawer.hidden = false;
	};

	const persistLayoutSettings = async () => {
		if (!layoutEditingBlock?.dataset.settingsUrl) {
			return;
		}
		const min = Math.max(1, Math.min(12, Number(columnsMinInput?.value || 1)));
		const max = Math.max(min, Math.min(12, Number(columnsMaxInput?.value || min)));
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("gallery[columnsMin]", String(min));
			formData.append("gallery[columnsMax]", String(max));
			if (layoutEditingBlock.dataset.templateCode) {
				formData.append("template", layoutEditingBlock.dataset.templateCode);
			}
			const response = await fetch(layoutEditingBlock.dataset.settingsUrl, {
				method: "POST",
				body: formData,
				credentials: "same-origin",
			});
			if (!response.ok) {
				throw new Error("layout failed");
			}
			layoutEditingBlock.dataset.columnsMin = String(min);
			layoutEditingBlock.dataset.columnsMax = String(max);
			await refreshBlock(layoutEditingBlock);
			const next = document.querySelector(`[data-builder-block][data-component-id="${layoutEditingBlock.dataset.componentId}"]`);
			if (next) {
				layoutEditingBlock = next;
			}
			endSave(true);
			layoutDrawer.hidden = true;
		} catch (error) {
			endSave(false);
		}
	};

	const removeGalleryFile = async (block, button) => {
		if (!confirm("Opravdu odebrat tuto fotku?")) {
			return;
		}
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			const response = await fetch(button.dataset.deleteUrl, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("delete file failed");
			}
			await refreshBlock(block);
			endSave(true);
		} catch (error) {
			endSave(false);
		}
	};

	const renderInsertPoints = () => {
		const rootList = document.querySelector("[data-builder-root]");
		if (!rootList) {
			return;
		}
		rootList.querySelectorAll("[data-builder-insert]").forEach((node) => node.remove());

		const sections = [...rootList.querySelectorAll(":scope > .page-component")];
		const insertBefore = (afterId) => {
			const point = document.createElement("button");
			point.type = "button";
			point.className = "builder-insert";
			point.dataset.builderInsert = "";
			point.dataset.afterId = afterId ? String(afterId) : "";
			point.textContent = "+ Přidat sem";
			point.addEventListener("click", (event) => {
				event.preventDefault();
				event.stopPropagation();
				closeDrawers();
				addDrawer.hidden = false;
				addDrawer.dataset.insertAfterId = afterId ? String(afterId) : "";
			});
			return point;
		};

		rootList.insertBefore(insertBefore(null), sections[0] || null);
		sections.forEach((section) => {
			const block = section.querySelector(":scope > [data-builder-block]");
			const afterId = block?.dataset.componentId || "";
			section.after(insertBefore(afterId));
		});
	};

	const bindBlock = (block) => {
		block.addEventListener("click", (event) => {
			if (event.target.closest("[data-builder-delete], [data-builder-replace-image], [data-builder-add-images], [data-builder-edit-pages], [data-builder-layout-settings], [data-builder-remove-file], .builder-block__media-action")) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			selectBlock(block);
			if (block.dataset.kind === "TEXT") {
				openTextEditor(block);
			}
		});

		block.querySelector("[data-builder-delete]")?.addEventListener("click", (event) => {
			event.preventDefault();
			event.stopPropagation();
			deleteBlock(block);
		});

		const fileInput = block.querySelector("[data-builder-file-input]");
		block.querySelector("[data-builder-replace-image], [data-builder-add-images]")?.addEventListener("click", (event) => {
			event.preventDefault();
			event.stopPropagation();
			fileInput?.click();
		});

		block.querySelector("[data-builder-edit-pages]")?.addEventListener("click", (event) => {
			event.preventDefault();
			event.stopPropagation();
			openPagesDrawer(block);
		});

		block.querySelectorAll("[data-builder-layout-settings]").forEach((button) => {
			button.addEventListener("click", (event) => {
				event.preventDefault();
				event.stopPropagation();
				openLayoutDrawer(block);
			});
		});

		block.querySelectorAll("[data-builder-remove-file]").forEach((button) => {
			button.addEventListener("click", (event) => {
				event.preventDefault();
				event.stopPropagation();
				removeGalleryFile(block, button);
			});
		});

		fileInput?.addEventListener("change", () => {
			const files = [...(fileInput.files || [])];
			fileInput.value = "";
			uploadFiles(block, files);
		});

		block.addEventListener("dragover", (event) => {
			if (block.dataset.uploadUrl) {
				event.preventDefault();
				block.classList.add("is-drop-target");
			}
		});
		block.addEventListener("dragleave", () => block.classList.remove("is-drop-target"));
		block.addEventListener("drop", (event) => {
			block.classList.remove("is-drop-target");
			if (!block.dataset.uploadUrl) {
				return;
			}
			event.preventDefault();
			const files = [...(event.dataTransfer?.files || [])].filter((file) => file.type.startsWith("image/"));
			uploadFiles(block, files);
		});
	};

	const persistGalleryOrder = async (block) => {
		const url = block.dataset.reorderFilesUrl;
		const gallery = block.querySelector(".builder-image-gallery");
		if (!url || !gallery) {
			return;
		}
		const ids = [...gallery.querySelectorAll("[data-storage-file-id]")].map((node) => node.dataset.storageFileId);
		beginSave();
		try {
			const formData = new FormData();
			formData.append("formToken", token);
			formData.append("storageFileOrder", ids.join(","));
			const response = await fetch(url, { method: "POST", body: formData, credentials: "same-origin" });
			if (!response.ok) {
				throw new Error("reorder files failed");
			}
			endSave(true);
		} catch (error) {
			endSave(false);
		}
	};

	const initSortables = () => {
		sortables.forEach((instance) => instance.destroy());
		sortables = [];

		const rootList = document.querySelector("[data-builder-root]");
		if (rootList) {
			sortables.push(Sortable.create(rootList, {
				animation: 150,
				handle: "[data-builder-drag]",
				draggable: ".page-component",
				onEnd: () => {
					persistTree();
					renderInsertPoints();
				},
			}));
		}

		document.querySelectorAll("[data-builder-columns]").forEach((columns) => {
			sortables.push(Sortable.create(columns, {
				animation: 150,
				handle: "[data-builder-drag]",
				draggable: "[data-builder-column-item]",
				group: "builder-columns",
				onEnd: () => persistTree(),
			}));
		});

		document.querySelectorAll(".builder-image-gallery").forEach((gallery) => {
			const block = gallery.closest("[data-builder-block]");
			sortables.push(Sortable.create(gallery, {
				animation: 150,
				draggable: ".builder-image-gallery__item",
				onEnd: () => persistGalleryOrder(block),
			}));
		});
	};

	document.querySelectorAll("[data-builder-block]").forEach(bindBlock);
	renderInsertPoints();

	canvas?.addEventListener("click", (event) => {
		if (event.target.closest("[data-builder-block]")) {
			return;
		}
		deselect();
	});

	canvas?.addEventListener("click", (event) => {
		const link = event.target.closest("a");
		if (link && canvas.contains(link)) {
			event.preventDefault();
		}
	});

	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape") {
			deselect();
			closeDrawers();
		}
		if ((event.key === "Backspace" || event.key === "Delete") && selected && !activeEditor && document.activeElement === document.body) {
			event.preventDefault();
			deleteBlock(selected);
		}
	});

	chrome.querySelector("[data-builder-toggle-settings]")?.addEventListener("click", () => {
		const open = settings.hidden;
		closeDrawers();
		settings.hidden = !open;
	});

	chrome.querySelector("[data-builder-toggle-add]")?.addEventListener("click", () => {
		const open = addDrawer.hidden;
		closeDrawers();
		addDrawer.hidden = !open;
	});

	chrome.querySelectorAll("[data-builder-width]").forEach((button) => {
		button.addEventListener("click", () => {
			const width = button.dataset.builderWidth;
			document.body.classList.toggle("builder--phone", width === "phone");
			chrome.querySelectorAll("[data-builder-width]").forEach((candidate) => {
				candidate.classList.toggle("is-active", candidate === button);
			});
		});
	});

	chrome.querySelector("[data-builder-pages-add-button]")?.addEventListener("click", () => {
		if (!pagesEditingBlock || !pagesAddSelect?.value) {
			return;
		}
		const pageId = Number(pagesAddSelect.value);
		const meta = candidatePages.find((page) => Number(page.pageId) === pageId);
		if (!meta) {
			return;
		}
		const linked = readLinkedPages(pagesEditingBlock);
		linked.push({
			pageId,
			title: meta.title,
			path: meta.path,
			template: pageGalleryTemplates[0]?.value || "IMAGE_AND_TEXT",
		});
		pagesEditingBlock.dataset.linkedPages = JSON.stringify(linked);
		persistPageLinks(pagesEditingBlock, linked).then(() => {
			const next = document.querySelector(`[data-builder-block][data-component-id="${pagesEditingBlock.dataset.componentId}"]`) || pagesEditingBlock;
			pagesEditingBlock = next;
			renderPagesDrawer(next);
		});
	});

	addDrawer?.querySelectorAll("[data-builder-add-kind]").forEach((button) => {
		button.addEventListener("click", () => {
			const kind = button.dataset.builderAddKind;
			const insertAfterId = addDrawer.dataset.insertAfterId || null;
			const parentId = !insertAfterId && selected?.dataset?.kind === "COLUMNS"
				? selected.dataset.componentId
				: null;
			createBlock(kind, parentId, insertAfterId);
		});
	});

	chrome.querySelector("[data-builder-layout-save]")?.addEventListener("click", () => {
		persistLayoutSettings();
	});

	initSortables();
}
