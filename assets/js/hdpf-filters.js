/**
 * Progressive enhancement for HDWebmobile Product Filters.
 *
 * The filter form is a plain <form method="get">. With this script, submitting it (or
 * changing a control) fetches the same URL in the background and swaps only the product
 * grid, instead of a full page reload. If anything is unexpected -- the products container
 * can't be found, the fetch fails -- it falls back to a normal navigation. No filter value
 * is handled here beyond being copied into a URL the server then validates.
 */
(function () {
	'use strict';

	var GRID_SELECTORS = [
		'.wp-block-woocommerce-product-template',
		'.wc-block-product-template',
		'ul.products',
		'.products'
	];
	var PAGINATION_SELECTORS = [
		'.woocommerce-pagination',
		'.wp-block-query-pagination',
		'.wc-block-pagination'
	];

	function findFirst(root, selectors) {
		for (var i = 0; i < selectors.length; i++) {
			var el = root.querySelector(selectors[i]);
			if (el) { return el; }
		}
		return null;
	}

	function run(form) {
		var url = form.action + (form.action.indexOf('?') === -1 ? '?' : '&') +
			new URLSearchParams(new FormData(form)).toString();

		var currentGrid = findFirst(document, GRID_SELECTORS);
		if (!currentGrid) { window.location.href = url; return; }

		form.classList.add('hdpf-loading');

		fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) {
				if (!r.ok) { throw new Error('bad status'); }
				return r.text();
			})
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var newGrid = findFirst(doc, GRID_SELECTORS);
				if (!newGrid) { throw new Error('no grid in response'); }

				var liveGrid = findFirst(document, GRID_SELECTORS);
				liveGrid.parentNode.replaceChild(document.importNode(newGrid, true), liveGrid);

				var newPag = findFirst(doc, PAGINATION_SELECTORS);
				var livePag = findFirst(document, PAGINATION_SELECTORS);
				if (livePag) {
					if (newPag) {
						livePag.parentNode.replaceChild(document.importNode(newPag, true), livePag);
					} else {
						livePag.remove();
					}
				}

				var newResult = doc.querySelector('.woocommerce-result-count');
				var liveResult = document.querySelector('.woocommerce-result-count');
				if (liveResult && newResult) { liveResult.textContent = newResult.textContent; }

				window.history.pushState({}, '', url);
				document.dispatchEvent(new CustomEvent('hdpf:filtered'));
			})
			.catch(function () { window.location.href = url; })
			.finally(function () { form.classList.remove('hdpf-loading'); });
	}

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (form && form.classList && form.classList.contains('hdpf-filters')) {
			e.preventDefault();
			run(form);
		}
	});

	// Auto-apply when a checkbox / select changes, for a snappier feel.
	document.addEventListener('change', function (e) {
		var form = e.target && e.target.closest ? e.target.closest('form.hdpf-filters') : null;
		if (form && e.target.matches('input[type="checkbox"], select')) {
			run(form);
		}
	});
})();
