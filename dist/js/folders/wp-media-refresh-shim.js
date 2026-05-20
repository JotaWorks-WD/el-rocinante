/**
 * El Rocinante — wp.media Upload Patch
 *
 * Patches wp.Uploader.prototype.success to:
 *   1. Call rociHandleAttachmentUploaded (sidebar count updates).
 *   2. Call rociAddUploadToLibrary to add the new attachment model to the
 *      active Backbone library collection if it belongs in the current view,
 *      restoring upload-to-grid sync that was lost when mirroring.fetch()
 *      was removed in v2.9.7.
 *
 * Also exposes two global helpers shared by the fauxlders JS modules:
 *   rociForceLibraryRefresh() — retained as a no-op since v2.9.7; callers
 *       in folders-sidebar.js and folders-dragdrop.js continue to work.
 *   rociWatchForReAdd( library, idSet ) — guards a Backbone library
 *       collection against re-addition of specific attachment IDs by
 *       in-flight more() XHRs (see inline JSDoc). Used by both
 *       folders-bulk.js (bulk move) and folders-dragdrop.js (single drag).
 *
 * v2.9.7: mirroring.fetch() removed — was undoing library.remove() calls.
 * v2.10.6: rociWatchForReAdd() + rociAddUploadToLibrary() added.
 *
 * File:    dist/js/folders/wp-media-refresh-shim.js
 * Version: 2.9.8
 * Updated: 2026-05-20
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// No-op since v2.9.7 — see file docblock.
	function rociForceLibraryRefresh() {}

	/**
	 * Guard a library collection against re-addition of specific attachment IDs
	 * by in-flight more() XHRs. WP's more() uses {remove:false, add:true}, so a
	 * stale XHR response can re-add models that library.remove() already removed.
	 * Listens for 3 s — long enough for any in-flight XHR to resolve — then tears
	 * down automatically.
	 *
	 * Shared by folders-bulk.js (bulk move) and folders-dragdrop.js (single drag).
	 *
	 * @param {wp.media.model.Attachments} library  Active Backbone library collection.
	 * @param {Set<string>}                idSet    String attachment IDs to guard.
	 */
	function rociWatchForReAdd( library, idSet ) {
		var onReAdd = function ( model ) {
			if ( idSet.has( String( model.id ) ) ) {
				library.remove( model );
			}
		};
		library.on( 'add', onReAdd );
		setTimeout( function () {
			library.off( 'add', onReAdd );
		}, 3000 );
	}

	/**
	 * Add a freshly uploaded attachment model to the active Backbone library
	 * collection if it belongs in the current view. Restores upload-to-grid sync
	 * for filtered views (WP core adds to Attachments.all but not to filtered
	 * library; without this call, uploads only appear after a page refresh).
	 *
	 * View-vs-target scenarios:
	 *   All Files view    — always add (upload is visible regardless of folder).
	 *   Unassigned view   — add only if upload had no target folder (picker empty).
	 *   Specific folder   — add only if upload's target term matches the filter term.
	 *   Nested folder     — same as specific folder (compared by term ID, not label).
	 *
	 * @param {wp.media.model.Attachment} attachment  Model from the success callback.
	 */
	function rociAddUploadToLibrary( attachment ) {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( ! library ) {
				return;
			}

			var props        = library.props;
			var filterFolder = props && props.get( 'roci_media_folder' );
			var isUnassigned = props && !! props.get( 'roci_no_folder' );

			var picker       = document.querySelector( '.roci-upload-picker__select' );
			var uploadTermId = ( picker && picker.value ) ? parseInt( picker.value, 10 ) : 0;

			var shouldAdd = false;

			if ( ! filterFolder && ! isUnassigned ) {
				// All Files view — always add.
				shouldAdd = true;
			} else if ( isUnassigned && ! uploadTermId ) {
				// Unassigned view, upload has no target folder — add.
				shouldAdd = true;
			} else if ( filterFolder && uploadTermId ) {
				// Specific or nested folder view — add only if target matches filter.
				shouldAdd = ( parseInt( filterFolder, 10 ) === uploadTermId );
			}

			if ( shouldAdd ) {
				library.add( attachment );
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
		wp.Uploader.prototype.success = function ( attachment ) {
			var result = origSuccess.apply( this, arguments );
			rociForceLibraryRefresh();
			if ( typeof window.rociHandleAttachmentUploaded === 'function' ) {
				window.rociHandleAttachmentUploaded( attachment );
			}
			rociAddUploadToLibrary( attachment );
			return result;
		};
		wp.Uploader.prototype._rociUploadRefreshPatched = true;
		return true;
	}

	window.rociForceLibraryRefresh = rociForceLibraryRefresh;
	window.rociWatchForReAdd       = rociWatchForReAdd;

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
