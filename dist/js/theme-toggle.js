/**
 * Theme Toggle — the visitor's light / dark choice (roci-dark-mode, "toggle")
 *
 * Enqueued by inc/dark-mode.php only on a site whose colour mode is "toggle".
 * A click on [data-roci-theme-toggle] flips the mode that is showing now —
 * data-theme on <html>, else the device's prefers-color-scheme — sets
 * data-theme and saves it in localStorage 'roci-theme', which the pre-paint
 * script reads on the next page. The labels and icons follow from CSS, so
 * nothing here writes text.
 *
 * File:    dist/js/theme-toggle.js
 * Version: 1.0.0
 * Updated: 2026-11-18
 */
( function () {
	'use strict';

	var root = document.documentElement;

	function current() {
		var set = root.getAttribute( 'data-theme' );
		if ( set === 'light' || set === 'dark' ) {
			return set;
		}
		return window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '[data-roci-theme-toggle]' ) : null;
		if ( ! button ) {
			return;
		}

		var next = current() === 'dark' ? 'light' : 'dark';
		root.setAttribute( 'data-theme', next );

		try {
			localStorage.setItem( 'roci-theme', next );
		} catch ( e ) {}
	} );
}() );
