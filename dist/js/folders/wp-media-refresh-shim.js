/**
 * El Rocinante — wp.media Upload Patch
 *
 * Patches wp.Uploader.prototype.success to call rociHandleAttachmentUploaded
 * on upload completion. Exposes rociForceLibraryRefresh() globally — retained
 * as a no-op since v2.9.7 so callers in folders-sidebar.js and
 * folders-dragdrop.js continue to work without errors.
 *
 * v2.9.7: library.mirroring.fetch({ reset: true }) and its debounced
 * setTimeout wrapper were removed. In v2.9.6 folders-dragdrop.js began
 * calling library.remove(model) directly (rociRemoveFromGrid) to remove the
 * moved attachment from the Backbone library collection. The delayed mirroring
 * refetch was then running collection.set(), which merges cached models
 * without honouring manual removals, undoing the v2.9.6 removal and leaving
 * the photo visible in both source and destination after an Unassigned →
 * folder drag. Removing the refetch lets the direct-manipulation approach in
 * folders-dragdrop.js be the sole grid update path.
 *
 * @package ElRocinante
 * @version 2.9.7
 * Updated: 2026-05-20
 */

( function () {

	'use strict';

	// No-op since v2.9.7 — see file docblock.
	function rociForceLibraryRefresh() {}

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
