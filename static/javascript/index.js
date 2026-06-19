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

document.addEventListener("DOMContentLoaded", initGallerySlideshows);
