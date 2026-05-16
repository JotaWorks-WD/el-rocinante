/* global wp */

/**
 * Bulk Organize button — placeholder in the grid-view toolbar.
 *
 * Injects a disabled "Bulk Organize" button at priority -70 in the
 * AttachmentsBrowser toolbar (the slot vacated by the removed folder-filter
 * dropdown). Functionality is placeholder / coming soon.
 *
 * Uses the same AttachmentsBrowser.createToolbar monkey-patch pattern as
 * the former media-folder-filter.js (see that file for the full pattern).
 *
 * File:    dist/js/folders/bulk-organize-button.js
 * Version: 1.0.0
 * Updated: 2026-05-16
 */

( function ( media ) {

	'use strict';

	if ( ! media || ! media.view || ! media.View ) {
		return;
	}

	var OriginalBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = OriginalBrowser.extend( {

		createToolbar: function () {
			OriginalBrowser.prototype.createToolbar.apply( this, arguments );

			var BulkOrganizeView = media.View.extend( {
				tagName:   'button',
				className: 'button roci-bulk-organize-btn',

				render: function () {
					this.el.setAttribute( 'type',     'button' );
					this.el.setAttribute( 'disabled', 'disabled' );
					this.el.setAttribute( 'title',    'Bulk Organize (coming soon)' );
					this.el.textContent = 'Bulk Organize';
					return this;
				}
			} );

			this.toolbar.set( 'rociBulkOrganize', new BulkOrganizeView( {
				priority: -70
			} ).render() );
		}
	} );

} ( wp.media ) );
