/* global wp, rociMediaFolders, rociAdminFolders, _ */

/**
 * Media Folder Filter — grid view and modal toolbar extension
 *
 * Injects a folder <select> as a SEPARATE control AFTER the existing type
 * and date filters — does NOT replace AttachmentFilters.All or .Uploaded.
 *
 * Toolbar order in grid view (left → right):
 *   [Type filter -80] [Date filter -75] [Folder filter -70] [+ New Folder -65]
 *   | [Bulk select  — primary/right section]
 *
 * Why AJAX filtering works without propmap changes:
 *   wp.media.model.Query.get() iterates every prop and maps it to a query arg
 *   via:  args[ Query.propmap[prop] || prop ] = value
 *   Because 'roci_media_folder' is not in propmap, the fallback (|| prop) uses
 *   the key as-is, so it lands in the admin-ajax payload automatically.
 *   The PHP ajax_query_attachments_args filter in filters.php reads
 *   $_REQUEST['query']['roci_media_folder'] directly (bypassing WP's
 *   whitelist) and applies the tax_query with include_children.
 *
 * Version: 1.4.0
 */

( function ( media ) {

	'use strict';

	if ( ! media || ! media.view ) {
		return;
	}

	// Declare roci_media_folder as a default query arg so it is always present
	// in the admin-ajax payload (empty string = "no filter", positive int = term_id).
	_.extend( media.model.Query.defaultArgs, { roci_media_folder: '' } );

	// Holds the most recently created RociMediaFolderFilter instance so
	// roci:folderCreated can re-render it after a term is created.
	var activeFilter = null;


	// ── Shared folder filter builder ───────────────────────────────────────

	function buildFolderFilters() {
		var filters = {};

		filters[ '' ] = {
			text:  rociMediaFolders.allLabel,
			props: { roci_media_folder: '' }
		};

		_.each( rociMediaFolders.terms, function ( term ) {
			filters[ term.term_id ] = {
				text:  term.name,
				props: { roci_media_folder: term.term_id }
			};
		} );

		return filters;
	}


	// ── Modal open helper ──────────────────────────────────────────────────

	function openModal() {
		var modal     = document.getElementById( 'roci-folder-modal' );
		var backdrop  = document.getElementById( 'roci-folder-backdrop' );
		var nameInput = document.getElementById( 'roci-folder-name' );
		var errorEl   = document.getElementById( 'roci-folder-error' );

		if ( ! modal || ! backdrop || ! nameInput ) {
			return;
		}

		nameInput.value = '';
		if ( errorEl ) {
			errorEl.textContent   = '';
			errorEl.style.display = 'none';
		}
		modal.style.display    = 'block';
		backdrop.style.display = 'block';
		nameInput.focus();
	}


	// ── RociMediaFolderFilter ──────────────────────────────────────────────
	//
	// Standalone AttachmentFilters subclass for folder filtering.
	// Extends the BASE wp.media.view.AttachmentFilters, NOT .All or .Uploaded,
	// so the existing type and date filters are completely untouched.

	var RociMediaFolderFilter = media.view.AttachmentFilters.extend( {

		id: 'roci-media-folder-filter',

		initialize: function () {
			media.view.AttachmentFilters.prototype.initialize.apply( this, arguments );
			activeFilter = this;
		},

		createFilters: function () {
			this.filters = buildFolderFilters();
		}
	} );


	// ── NewFolderButton ────────────────────────────────────────────────────

	var NewFolderButton = media.View.extend( {
		tagName:    'button',
		className:  'button roci-new-folder-btn',
		attributes: { type: 'button' },

		initialize: function () {
			this.el.textContent = rociAdminFolders.i18n.newFolderLabel;
			this.el.addEventListener( 'click', openModal );
		},

		render: function () {
			return this;
		}
	} );


	// ── AttachmentsBrowser extension ───────────────────────────────────────
	//
	// Injects the folder filter and (on upload.php) the "+ New Folder" button
	// after WP's type (-80) and date (-75) filters in the secondary toolbar.
	// Priority -70 for the folder filter, -65 for the button places them in
	// the secondary (left) section immediately before the primary (right)
	// Bulk select section.

	var OriginalBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = OriginalBrowser.extend( {

		createToolbar: function () {
			OriginalBrowser.prototype.createToolbar.apply( this, arguments );

			// Evaluated here, not at module load, because rociAdminFolders is
			// enqueued without a dependency on this script and may not yet be
			// defined at parse time.
			var hasModal = typeof rociAdminFolders !== 'undefined';

			// Show the folder filter when folders exist, or always on upload.php
			// so it appears before any folder is created (enabling the button).
			if ( rociMediaFolders.terms.length || hasModal ) {
				this.toolbar.set( 'rociMediaFolderFilter', new RociMediaFolderFilter( {
					controller: this.controller,
					model:      this.collection.props,
					priority:   -70
				} ).render() );
			}

			// Button only on upload.php — post-editor modal has no modal HTML.
			if ( hasModal ) {
				this.toolbar.set( 'rociNewFolderBtn', new NewFolderButton( {
					priority: -65
				} ).render() );
			}
		}
	} );


	// ── Post-creation refresh ──────────────────────────────────────────────
	//
	// admin-folders.js dispatches roci:folderCreated after a successful AJAX
	// creation. The options payload uses {value, label} format; convert to
	// {term_id, name} before storing. Rebuild this.filters before render so
	// the select options reflect the updated term list.

	document.addEventListener( 'roci:folderCreated', function ( e ) {
		rociMediaFolders.terms = e.detail.options.map( function ( opt ) {
			return { term_id: opt.value, name: opt.label };
		} );

		if ( activeFilter ) {
			activeFilter.createFilters();
			activeFilter.render();
		}
	} );

}( wp.media ) );
