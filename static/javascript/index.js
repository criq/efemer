const initGallerySlideshows = () => {
	document.querySelectorAll("[data-gallery-slideshow]").forEach((root) => {
		const track = root.querySelector("[data-gallery-slideshow-track]");
		const slides = [...track.querySelectorAll(".page-gallery-slideshow__slide")];

		if (!slides.length) {
			return;
		}

		let index = 0;

		const goTo = (nextIndex) => {
			index = (nextIndex + slides.length) % slides.length;
			track.style.transform = `translateX(-${index * 100}%)`;
			root.querySelectorAll("[data-gallery-slideshow-dots] button").forEach((dot, dotIndex) => {
				dot.setAttribute("aria-current", dotIndex === index ? "true" : "false");
			});
		};

		const prevButton = root.querySelector("[data-gallery-slideshow-prev]");
		const nextButton = root.querySelector("[data-gallery-slideshow-next]");
		const dots = root.querySelector("[data-gallery-slideshow-dots]");

		if (prevButton) {
			prevButton.addEventListener("click", () => goTo(index - 1));
		}

		if (nextButton) {
			nextButton.addEventListener("click", () => goTo(index + 1));
		}

		if (dots && slides.length > 1) {
			slides.forEach((slide, slideIndex) => {
				const dot = document.createElement("button");
				dot.type = "button";
				dot.className = "page-gallery-slideshow__dot";
				dot.setAttribute("aria-label", `Snímek ${slideIndex + 1}`);
				dot.addEventListener("click", () => goTo(slideIndex));
				dots.appendChild(dot);
			});
		}

		goTo(0);
	});
};

const initSiteNav = () => {
	const header = document.querySelector(".site-header");
	const toggle = document.querySelector(".site-nav-toggle");
	const nav = document.querySelector("#site-nav");

	if (!header || !toggle || !nav) {
		return;
	}

	const setOpen = (open) => {
		header.classList.toggle("is-nav-open", open);
		toggle.setAttribute("aria-expanded", open ? "true" : "false");
		toggle.setAttribute("aria-label", open ? "Zavřít menu" : "Otevřít menu");
	};

	toggle.addEventListener("click", () => {
		setOpen(!header.classList.contains("is-nav-open"));
	});

	nav.querySelectorAll("a").forEach((link) => {
		link.addEventListener("click", () => setOpen(false));
	});

	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape") {
			setOpen(false);
		}
	});

	document.addEventListener("click", (event) => {
		if (!header.contains(event.target)) {
			setOpen(false);
		}
	});
};

const initLightboxGalleries = () => {
	const galleries = [...document.querySelectorAll("[data-lightbox-gallery]")];
	if (!galleries.length) {
		return;
	}

	const root = document.createElement("div");
	root.className = "lightbox";
	root.hidden = true;
	root.setAttribute("role", "dialog");
	root.setAttribute("aria-modal", "true");
	root.setAttribute("aria-label", "Galerie");
	root.innerHTML = `
		<button type="button" class="lightbox__close" data-lightbox-close aria-label="Zavřít">×</button>
		<button type="button" class="lightbox__prev" data-lightbox-prev aria-label="Předchozí">‹</button>
		<figure class="lightbox__figure">
			<img class="lightbox__img" data-lightbox-img alt="">
			<figcaption class="lightbox__caption" data-lightbox-caption hidden></figcaption>
		</figure>
		<button type="button" class="lightbox__next" data-lightbox-next aria-label="Další">›</button>
		<div class="lightbox__counter" data-lightbox-counter></div>
	`;
	document.body.appendChild(root);

	const imgEl = root.querySelector("[data-lightbox-img]");
	const captionEl = root.querySelector("[data-lightbox-caption]");
	const counterEl = root.querySelector("[data-lightbox-counter]");
	const closeBtn = root.querySelector("[data-lightbox-close]");
	const prevBtn = root.querySelector("[data-lightbox-prev]");
	const nextBtn = root.querySelector("[data-lightbox-next]");

	let items = [];
	let index = 0;

	const render = () => {
		const item = items[index];
		if (!item) {
			return;
		}
		imgEl.src = item.src;
		imgEl.alt = item.caption || "";
		if (item.caption) {
			captionEl.hidden = false;
			captionEl.textContent = item.caption;
		} else {
			captionEl.hidden = true;
			captionEl.textContent = "";
		}
		counterEl.textContent = `${index + 1} / ${items.length}`;
		const multi = items.length > 1;
		prevBtn.hidden = !multi;
		nextBtn.hidden = !multi;
	};

	const open = (galleryItems, startIndex) => {
		items = galleryItems;
		index = startIndex;
		root.hidden = false;
		requestAnimationFrame(() => root.classList.add("is-open"));
		document.body.classList.add("is-lightbox-open");
		render();
		closeBtn.focus();
	};

	const close = () => {
		root.classList.remove("is-open");
		document.body.classList.remove("is-lightbox-open");
		window.setTimeout(() => {
			if (!root.classList.contains("is-open")) {
				root.hidden = true;
				imgEl.removeAttribute("src");
			}
		}, 200);
	};

	const go = (delta) => {
		if (items.length < 2) {
			return;
		}
		index = (index + delta + items.length) % items.length;
		render();
	};

	galleries.forEach((gallery) => {
		const links = [...gallery.querySelectorAll("[data-lightbox-item]")];
		const galleryItems = links.map((link) => ({
			src: link.getAttribute("href"),
			caption: link.dataset.caption || "",
		}));

		links.forEach((link, linkIndex) => {
			link.addEventListener("click", (event) => {
				event.preventDefault();
				// Full-bleed photos on small screens — no lightbox.
				if (window.matchMedia("(max-width: 700px)").matches) {
					return;
				}
				open(galleryItems, linkIndex);
			});
		});
	});

	closeBtn.addEventListener("click", close);
	prevBtn.addEventListener("click", () => go(-1));
	nextBtn.addEventListener("click", () => go(1));
	root.addEventListener("click", (event) => {
		if (event.target === root) {
			close();
		}
	});

	document.addEventListener("keydown", (event) => {
		if (!root.classList.contains("is-open")) {
			return;
		}
		if (event.key === "Escape") {
			close();
		} else if (event.key === "ArrowLeft") {
			go(-1);
		} else if (event.key === "ArrowRight") {
			go(1);
		}
	});
};

document.addEventListener("DOMContentLoaded", () => {
	initGallerySlideshows();
	initSiteNav();
	initLightboxGalleries();
});
