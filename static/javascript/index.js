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

	// Delegation so infinitely loaded feed items keep working.
	document.addEventListener("click", (event) => {
		const link = event.target.closest("[data-lightbox-item]");
		if (!link) {
			return;
		}
		const gallery = link.closest("[data-lightbox-gallery]");
		if (!gallery) {
			return;
		}
		event.preventDefault();
		if (window.matchMedia("(max-width: 700px)").matches) {
			return;
		}
		const links = [...gallery.querySelectorAll("[data-lightbox-item]")];
		const galleryItems = links.map((item) => ({
			src: item.getAttribute("href"),
			caption: item.dataset.caption || "",
		}));
		open(galleryItems, Math.max(0, links.indexOf(link)));
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

const initFeeds = () => {
	// Preload when the sentinel is still ~2.5 viewports below the fold.
	const FEED_ROOT_MARGIN = "0px 0px 250% 0px";
	// Active post = the one intersecting a band around the viewport center.
	const URL_ROOT_MARGIN = "-35% 0px -45% 0px";

	document.querySelectorAll("[data-feed]").forEach((feed) => {
		const listTitle = document.title;
		let activePath = window.location.pathname;
		const observed = new WeakSet();

		const applyFeedUrl = (item) => {
			const path = item?.dataset?.feedUrl;
			if (!path || path === activePath) {
				return;
			}
			activePath = path;
			const title = item.dataset.feedTitle || listTitle;
			document.title = title;
			window.history.replaceState({ feedUrl: path }, title, path);
		};

		const urlObserver = new IntersectionObserver(
			(entries) => {
				const visible = entries
					.filter((entry) => entry.isIntersecting)
					.sort((a, b) => b.intersectionRatio - a.intersectionRatio);
				if (visible[0]) {
					applyFeedUrl(visible[0].target);
				}
			},
			{ rootMargin: URL_ROOT_MARGIN, threshold: [0, 0.1, 0.25, 0.5, 0.75] },
		);

		const observeFeedItems = (root) => {
			root.querySelectorAll("[data-feed-url]").forEach((item) => {
				if (observed.has(item)) {
					return;
				}
				observed.add(item);
				urlObserver.observe(item);
			});
		};

		observeFeedItems(feed);

		const sentinel = feed.querySelector("[data-feed-sentinel]");
		if (!sentinel) {
			return;
		}

		let nextUrl = feed.dataset.feedNext || "";
		let loading = false;

		const sentinelInPreloadZone = () => {
			if (!sentinel.isConnected) {
				return false;
			}
			const rect = sentinel.getBoundingClientRect();
			const preload = window.innerHeight * 2.5;

			return rect.top <= window.innerHeight + preload;
		};

		const loadMore = async () => {
			if (!nextUrl || loading) {
				return;
			}
			loading = true;
			feed.classList.add("is-loading");
			try {
				const response = await fetch(nextUrl, {
					credentials: "same-origin",
					headers: { Accept: "text/html" },
				});
				if (!response.ok) {
					throw new Error(`Feed HTTP ${response.status}`);
				}
				const html = (await response.text()).trim();
				nextUrl = response.headers.get("X-Feed-Next") || "";
				if (nextUrl) {
					feed.dataset.feedNext = nextUrl;
				} else {
					delete feed.dataset.feedNext;
				}
				if (html) {
					sentinel.insertAdjacentHTML("beforebegin", html);
					observeFeedItems(feed);
				}
				if (!nextUrl) {
					sentinel.remove();
					loadObserver.disconnect();
				}
			} catch (error) {
				console.error(error);
			} finally {
				loading = false;
				feed.classList.remove("is-loading");
				// Observer may stay intersecting after insert — keep fetching while in zone.
				if (nextUrl && sentinelInPreloadZone()) {
					loadMore();
				}
			}
		};

		const loadObserver = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						loadMore();
					}
				});
			},
			{ rootMargin: FEED_ROOT_MARGIN, threshold: 0 },
		);

		loadObserver.observe(sentinel);
	});
};

const initReveal = () => {
	const nodes = [...document.querySelectorAll(".site--denik [data-reveal]")];
	if (!nodes.length) {
		return;
	}

	if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
		nodes.forEach((node) => node.classList.add("is-in"));
		return;
	}

	const observer = new IntersectionObserver(
		(entries) => {
			entries.forEach((entry) => {
				if (!entry.isIntersecting) {
					return;
				}
				entry.target.classList.add("is-in");
				observer.unobserve(entry.target);
			});
		},
		{
			rootMargin: "0px 0px -6% 0px",
			threshold: 0.12,
		},
	);

	nodes.forEach((node) => observer.observe(node));
};

const initNewsletterForms = () => {
	document.querySelectorAll("[data-newsletter-form]").forEach((form) => {
		const input = form.querySelector('input[type="email"]');
		const button = form.querySelector('button[type="submit"]');
		const status = form.querySelector("[data-newsletter-status]");
		if (!input || !button || !status) {
			return;
		}

		const setStatus = (message, kind) => {
			status.hidden = !message;
			status.textContent = message || "";
			status.classList.toggle("is-error", kind === "error");
			status.classList.toggle("is-ok", kind === "ok");
		};

		form.addEventListener("submit", (event) => {
			event.preventDefault();
			const email = (input.value || "").trim();
			if (!email || !input.checkValidity()) {
				setStatus("Zadejte platný e-mail.", "error");
				input.focus();
				return;
			}

			if (typeof window.lhi !== "function") {
				setStatus("Odběr teď nejde odeslat. Zkuste to prosím znovu.", "error");
				return;
			}

			button.disabled = true;
			setStatus("Přihlašuji…", null);

			try {
				// identify links the browser to the contact and can subscribe in one call
				// (Leadhub onboarding watches for Identify; list codes go in `subscribe`).
				window.lhi("identify", {
					email,
					subscribe: ["newsletters"],
				});
				setStatus("Děkujeme — jste přihlášeni.", "ok");
				form.reset();
			} catch (error) {
				setStatus("Odběr se nepodařilo odeslat. Zkuste to prosím znovu.", "error");
			} finally {
				button.disabled = false;
			}
		});
	});
};

document.addEventListener("DOMContentLoaded", () => {
	initGallerySlideshows();
	initSiteNav();
	initLightboxGalleries();
	initReveal();
	initFeeds();
	initNewsletterForms();
});
