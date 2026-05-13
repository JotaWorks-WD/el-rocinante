/* global wp, rociMediaFolders, rociAdminFolders, _ */

/**
 * Media Folder Filter — grid view and modal toolbar extension
 *
 * Overrides wp.media.view.AttachmentFilters.All (modal) and
 * wp.media.view.AttachmentFilters.Uploaded (grid view) so the built-in
 * filter <select> shows folder options in both contexts. On upload.php,
 * also injects a "+ New Folder" button next to the filter via the
 * AttachmentsBrowser createToolbar hook.
 *
 * How filtering works:
 *   Selecting a folder sets roci_media_folder on the collection props.
 *   This prop travels through the query-attachments AJAX request.
 *   The PHP ajax_query_attachments_args filter in inc/folders/filters.php
 *   picks it up and applies the tax_query.
 *
 * How the grid view button works:
 *   The button triggers the modal managed by admin-folders.js. After
 *   creation, admin-folders.js dispatches roci:folderCreated; this script
 *   updates rociMediaFolders.terms and re-renders the active filter.
 *
 * Version: 1.3.0
 */

( function ( media ) {

	'use strict';

	if ( ! media || ! media.view ) {
		return;
	}

	// Holds the most recently focused filter instance so roci:folderCreated
	// can refresh it after a term is created.
	var activeFilter = null;


	// ── Shared folder filter builder ───────────────────────────────────────
	//
	// Returns a filters hash suitable for this.filters in any AttachmentFilters
	// subclass. Reads from rociMediaFolders.terms so it reflects the current
	// term list at call time (important when called after term creation).

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


	// ── AttachmentFilters.All override (modal / non-grid) ──────────────────
	//
	// Replaces the MIME-type dropdown with a folder dropdown in the
	// Insert Media and Featured Image modals.

	var OriginalAll = media.view.AttachmentFilters.All;

	media.view.AttachmentFilters.All = OriginalAll.extend( {

		initialize: function () {
			OriginalAll.prototype.initialize.apply( this, arguments );
			activeFilter = this;
		},

		createFilters: function () {
			this.filters = buildFolderFilters();
		}
	} );


	// ── AttachmentFilters.Uploaded override (grid view) ────────────────────
	//
	// Replaces the uploaded-to dropdown with a folder dropdown in the
	// standalone media library grid view on upload.php.

	var OriginalUploaded = media.view.AttachmentFilters.Uploaded;

	media.view.AttachmentFilters.Uploaded = OriginalUploaded.extend( {

		initialize: function () {
			OriginalUploaded.prototype.initialize.apply( this, arguments );
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
	// The folder filter itself is handled by the .All / .Uploaded overrides
	// above. createToolbar only needs to inject the "+ New Folder" button,
	// and only on upload.php where the modal HTML exists.

	var OriginalBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = OriginalBrowser.extend( {

		createToolbar: function () {
			OriginalBrowser.prototype.createToolbar.apply( this, arguments );

			// Evaluated here (not at module load) to guarantee rociAdminFolders
			// is defined — footer scripts may execute after this module loads.
			var hasModal = typeof rociAdminFolders !== 'undefined';

			if ( hasModal ) {
				this.toolbar.set( 'rociNewFolderBtn', new NewFolderButton( {
					// Priority -79 puts the button in the secondary (left) section,
					// immediately to the right of the folder filter at priority -80.
					priority: -79
				} ).render() );
			}
		}
	} );


	// ── Post-creation refresh ──────────────────────────────────────────────
	//
	// admin-folders.js dispatches roci:folderCreated after a successful AJAX
	// creation. The options payload uses {value, label} format (from PHP's
	// roci_build_folder_options_for_select); rociMediaFolders.terms expects
	// {term_id, name} — convert before storing.
	//
	// createFilters() is called explicitly before render() so this.filters
	// is rebuilt from the updated term list regardless of whether the
	// AttachmentFilters base class calls createFilters inside render().

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
