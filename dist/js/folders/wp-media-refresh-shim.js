/**
 * El Rocinante — wp.media Refresh Shim
 *
 * Forces wp.media's library to re-fetch after upload completion,
 * sidestepping wp.media's broken reactive event chain in grid mode.
 * Exposes rociForceLibraryRefresh() globally so folders-sidebar.js's
 * destroy callback can trigger the same re-fetch on delete.
 *
 * @package ElRocinante
 * @version 2.8.16
 * Updated: 2026-05-19
 */

( function () {

	'use strict';

	var refreshTimer        = null;
	var REFRESH_DEBOUNCE_MS = 500;

	function performLibraryRefresh() {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( ! library ) {
				return;
			}
			// Prefer fetching the existing mirror query directly. _requery(true)
			// passes cache:false to Query.get(), which caches the Query object in
			// Query.all. On subsequent calls, Query.get() returns the same cached
			// object and mirror() no-ops (this.mirroring === query early-return),
			// so the grid never refreshes after the first drag in a filtered view.
			// Fetching the mirror directly re-requests server data every time.
			if ( library.mirroring && typeof library.mirroring.fetch === 'function' ) {
				library.mirroring.fetch( { reset: true } );
			} else if ( typeof library._requery === 'function' ) {
				library._requery( true );
			} else if ( typeof library.more === 'function' ) {
				library.more( { reset: true } );
			} else if ( typeof library.fetch === 'function' ) {
				library.fetch( { reset: true } );
			}
		} catch ( e ) {}
	}

	function rociForceLibraryRefresh() {
		if ( refreshTimer ) {
			clearTimeout( refreshTimer );
		}
		refreshTimer = setTimeout( function () {
			refreshTimer = null;
			performLibraryRefresh();
		}, REFRESH_DEBOUNCE_MS );
	}

	function patchUploaderSuccess() {
		if ( ! window.wp || ! wp.Uploader || ! wp.Uploader.prototype ) {
			return false;
		}
		if ( wp.Uploader.prototype._rociUploadRefreshPatched ) {
			return true;
		}
		var origSuccess = wp.Uploader.prototype.success;
		wp.Uploader.prototype.success = function ( attachment ) {
			var result = origSuccess.apply( this, arguments );
			rociForceLibraryRefresh();
			if ( typeof window.rociHandleAttachmentUploaded === 'function' ) {
				window.rociHandleAttachmentUploaded( attachment );
			}
			return result;
		};
		wp.Uploader.prototype._rociUploadRefreshPatched = true;
		return true;
	}

	window.rociForceLibraryRefresh = rociForceLibraryRefresh;

	( function () {
		if ( patchUploaderSuccess() ) {
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( patchUploaderSuccess() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();

} )();
