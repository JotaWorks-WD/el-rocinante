/**
 * Visitor Stats — one beacon per page load (roci-stats)
 *
 * Enqueued by inc/stats/stats.php for logged-out visitors only. Sends one
 * empty POST to rociStats.endpoint (roci/v1/hit); the server does the
 * counting. Silent for a logged-in page, the admin bar, automation
 * (navigator.webdriver) and a prerendered page that was never shown.
 *
 * File:    dist/js/stats.js
 * Version: 1.0.0
 * Updated: 2026-11-18
 */
( function () {
	'use strict';

	var cfg = window.rociStats;
	if ( ! cfg || ! cfg.endpoint ) { return; }
	if ( document.body.classList.contains( 'logged-in' ) || document.getElementById( 'wpadminbar' ) ) { return; }
	if ( navigator.webdriver ) { return; }

	function send() {
		if ( navigator.sendBeacon && navigator.sendBeacon( cfg.endpoint ) ) { return; }
		if ( window.fetch ) { fetch( cfg.endpoint, { method: 'POST', keepalive: true, credentials: 'omit' } ).catch( function () {} ); }
	}

	// A prerendered page counts only once it is actually shown.
	if ( document.prerendering || document.visibilityState === 'prerender' ) {
		document.addEventListener( 'prerenderingchange', send, { once: true } );
		return;
	}

	send();
}() );
