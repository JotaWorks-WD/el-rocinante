/**
 * El Rocinante — wp.media Refresh Shim
 *
 * Forces wp.media's library to re-fetch after upload completion,
 * sidestepping wp.media's broken reactive event chain in grid mode.
 * Exposes rociForceLibraryRefresh() globally so folders-sidebar.js's
 * destroy callback can trigger the same re-fetch on delete.
 *
 * @package ElRocinante
 * @version 2.8.13
 * Updated: 2026-05-16
 */

( function () {

	'use strict';

	function rociForceLibraryRefresh() {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( ! library ) {
				return;
			}
			if ( typeof library._requery === 'function' ) {
				library._requery( true );
			} else if ( typeof library.more === 'function' ) {
				library.more( { reset: true } );
			} else if ( typeof library.fetch === 'function' ) {
				library.fetch( { reset: true } );
			}
		} catch ( e ) {}
	}

	function patchUploaderSuccess() {
		if ( ! window.wp || ! wp.Uploader || ! wp.Uploader.prototype ) {
			return false;
		}
		if ( wp.Uploader.prototype._rociUploadRefreshPatched ) {
			return true;
		}
		var origSuccess = wp.Uploader.prototype.success;
		wp.Uploader.prototype.success = function () {
			var result = origSuccess.apply( this, arguments );
			rociForceLibraryRefresh();
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
