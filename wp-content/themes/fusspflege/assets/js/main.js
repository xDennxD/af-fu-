/**
 * Fußpflege One-Pager – Frontend-JS.
 * Wird in functions.php per wp_enqueue_script() mit "defer" geladen.
 * Kein jQuery, keine externen Libraries.
 */
(function () {
	'use strict';

	// Mobile Navigation: Button schaltet die Klasse "open" am Header und aria-expanded.
	var header = document.getElementById('menu');
	var button = document.querySelector('.menu-btn');

	if (header && button) {
		button.addEventListener('click', function () {
			var open = header.classList.toggle('open');
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		// Nach Klick auf einen Menüpunkt (Ankerlink) das Menü wieder schließen.
		header.querySelectorAll('.nav-list a').forEach(function (link) {
			link.addEventListener('click', function () {
				header.classList.remove('open');
				button.setAttribute('aria-expanded', 'false');
			});
		});
	}

	// Sanftes Einblenden beim Scrollen – nur, wenn der Nutzer Animationen nicht reduziert hat.
	var reduziert = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	if (!reduziert && 'IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}
				entry.target.animate(
					[{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'translateY(0)' }],
					{ duration: 600, easing: 'ease-out', fill: 'both' }
				);
				observer.unobserve(entry.target);
			});
		}, { threshold: 0.15 });

		document.querySelectorAll('section .container, .card').forEach(function (el) {
			observer.observe(el);
		});
	}
})();
