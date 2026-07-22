/**
 * Ad Placr front-end tracking: viewability impressions + clicks.
 *
 * @package AdPlacr
 * @since 2.5.0
 */
(function () {
	'use strict';

	if (typeof window.adPlacrTrack !== 'object' || !window.adPlacrTrack.restUrl) {
		return;
	}

	var restUrl = window.adPlacrTrack.restUrl;
	var nonce = window.adPlacrTrack.nonce || '';

	/**
	 * @param {string} event
	 * @param {Element} el
	 */
	function send(event, el) {
		var adId = parseInt(el.getAttribute('data-ad-id') || '0', 10);
		var placementId = parseInt(el.getAttribute('data-placement-id') || '0', 10);
		if (!adId) {
			return;
		}

		var body = {
			event: event,
			ad_id: adId,
			placement_id: placementId
		};

		try {
			fetch(restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				body: JSON.stringify(body)
			}).catch(function () {
				/* swallow network errors — tracking must not break the page */
			});
		} catch (e) {
			/* ignore */
		}
	}

	function observeImpressions() {
		var nodes = document.querySelectorAll('.ad-placr[data-ad-id]');
		if (!nodes.length || typeof IntersectionObserver === 'undefined') {
			return;
		}

		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting || entry.intersectionRatio < 0.5) {
						return;
					}
					var el = entry.target;
					if (el.getAttribute('data-ad-placr-impressed') === '1') {
						return;
					}
					el.setAttribute('data-ad-placr-impressed', '1');
					io.unobserve(el);
					send('impression', el);
				});
			},
			{ threshold: [0.5] }
		);

		nodes.forEach(function (el) {
			io.observe(el);
		});
	}

	function bindClicks() {
		document.addEventListener(
			'click',
			function (ev) {
				var t = ev.target;
				if (!t || !t.closest) {
					return;
				}
				var el = t.closest('.ad-placr[data-ad-id]');
				if (!el || el.getAttribute('data-ad-placr-clicked') === '1') {
					return;
				}
				el.setAttribute('data-ad-placr-clicked', '1');
				send('click', el);
			},
			true
		);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			observeImpressions();
			bindClicks();
		});
	} else {
		observeImpressions();
		bindClicks();
	}
})();
