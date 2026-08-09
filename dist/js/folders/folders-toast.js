/**
 * Folders — Shared Toast Notification
 *
 * One toast implementation for the whole Fauxlders admin surface. Consolidated
 * from two byte-near-identical copies that lived in folders-bulk.js and
 * folders-list-dragdrop.js; the copies had drifted only in the function name,
 * two comments, and where the Undo label was read from.
 *
 * THIS IS THE FIRST SHARED UTILITY IN dist/js/folders/. Every other file here
 * is a self-contained feature script with no cross-file dependency. The export
 * below is deliberate and is the only reason this file has a global: both
 * consumers wrap themselves in an IIFE, so a private function could not be
 * reached across files.
 *
 * WHY THE LABEL IS A PARAMETER. The two consumers receive different
 * wp_localize_script payloads — rociFoldersBulk on upload.php, rociDragDrop on
 * edit.php — so a shared function cannot read either global. The caller passes
 * opts.undoLabel instead. That was the ONLY real divergence between the copies;
 * timings, classes, ARIA and DOM target were already identical.
 *
 * LOAD ORDER IS GUARANTEED BY WP, NOT BY LUCK. This file registers as
 * 'roci-folders-toast' and is declared a dependency of both consumers, so
 * WordPress prints it first. Do not rely on enqueue call order.
 *
 * File:    dist/js/folders/folders-toast.js
 * Version: 1.0.0
 * Updated: 2026-08-09
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// Module-scope, shared across every caller on the page. The two consumers
	// never load on the same screen (upload.php vs edit.php), so in practice
	// this is still one toast at a time per surface — but the replace-in-flight
	// guard below makes that true even if they ever did co-occur.
	var currentToast   = null;
	var currentTimeout = null;

	/**
	 * Show a toast.
	 *
	 * @param {Object}   opts
	 * @param {string}   opts.message       Text to display. Required.
	 * @param {number}   [opts.duration]    Auto-dismiss delay in ms. Default 8000.
	 * @param {Function} [opts.undoCallback] Presence renders the Undo button.
	 * @param {string}   [opts.undoLabel]   Localized Undo label. Falls back to 'Undo'.
	 */
	function rociShowToast( opts ) {

		// Replace any in-flight toast immediately.
		if ( currentToast && currentToast.parentNode ) {
			currentToast.parentNode.removeChild( currentToast );
		}
		if ( currentTimeout ) {
			clearTimeout( currentTimeout );
			currentTimeout = null;
		}

		var toast = document.createElement( 'div' );
		toast.className = 'roci-toast';
		toast.setAttribute( 'role', 'alert' );
		toast.setAttribute( 'aria-live', 'polite' );

		var msgEl = document.createElement( 'span' );
		msgEl.className   = 'roci-toast__message';
		msgEl.textContent = opts.message;
		toast.appendChild( msgEl );

		if ( opts.undoCallback ) {
			var undoBtn = document.createElement( 'button' );
			undoBtn.type        = 'button';
			undoBtn.className   = 'roci-toast__undo';
			// Defensive default: a missing label must not render an empty button.
			undoBtn.textContent = opts.undoLabel || 'Undo';
			undoBtn.addEventListener( 'click', function () {
				dismiss();
				opts.undoCallback();
			} );
			toast.appendChild( undoBtn );
		}

		var closeBtn = document.createElement( 'button' );
		closeBtn.type      = 'button';
		closeBtn.className = 'roci-toast__close';
		closeBtn.setAttribute( 'aria-label', 'Dismiss' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', dismiss );
		toast.appendChild( closeBtn );

		function dismiss() {
			if ( currentTimeout ) {
				clearTimeout( currentTimeout );
				currentTimeout = null;
			}
			toast.classList.remove( 'roci-toast--visible' );
			toast.classList.add( 'roci-toast--hiding' );
			// 220ms must stay in step with the transition in
			// Build/scss/admin/_admin-folders-dragdrop.scss (.roci-toast).
			setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
				if ( currentToast === toast ) {
					currentToast = null;
				}
			}, 220 );
		}

		document.body.appendChild( toast );
		currentToast = toast;

		// Trigger entrance animation on next frame so the transition fires.
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				toast.classList.add( 'roci-toast--visible' );
			} );
		} );

		currentTimeout = setTimeout( dismiss, opts.duration || 8000 );
	}

	// The single export. Both consumers call this; nothing else in the folders
	// JS set touches it.
	window.rociShowToast = rociShowToast;

} )();
