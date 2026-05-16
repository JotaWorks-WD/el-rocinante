/* global wp, rociMediaFolders, rociAdminFolders, _ */

/**
 * Media Folder Filter — grid view and modal toolbar extension
 *
 * Injects a folder <select> as a SEPARATE control AFTER the existing type
 * and date filters — does NOT replace AttachmentFilters.All or .Uploaded.
 *
 * Toolbar order in grid view (left → right):
 *   [Type filter -80] [Date filter -75] [Folder filter -70]
 *   | [Bulk select  — primary/right section]
 *
 * Why AJAX filtering works without propmap changes:
 *   wp.media.model.Query.get() iterates every prop and maps it to a query arg
 *   via:  args[ Query.propmap[prop] || prop ] = value
 *   Because 'roci_media_folder' is not in propmap, the fallback (|| prop) uses
 *   the key as-is, so it lands in the admin-ajax payload automatically.
 *   The PHP ajax_query_attachments_args filter in filters.php reads
 *   $_REQUEST['query']['roci_media_folder'] directly and applies the tax_query.
 *
 * roci_no_folder follows the same propmap bypass: setting it to 1 in the model
 * causes the PHP filter to apply a NOT EXISTS tax_query for unassigned files.
 *
 * Version: 1.7.2
 * Updated: 2026-05-16
 */

( function ( media ) {

	'use strict';

	if ( ! media || ! media.view ) {
		return;
	}

	// Declare both custom props as default query args so they are always
	// present in the admin-ajax payload (empty string = no filter active).
	_.extend( media.model.Query.defaultArgs, {
		roci_media_folder: '',
		roci_no_folder:    ''
	} );

	// Holds the most recently created RociMediaFolderFilter instance so
	// roci:folderCreated and roci:sidebarFilter can update its model.
	var activeFilter = null;


	// ── Sync .view-list href with current Backbone folder filter ───────────
	//
	// In grid mode the active folder lives only in the Backbone model, not
	// in the URL. When the user clicks the list-view toggle, the href must
	// carry roci_media_folder so PHP can apply the filter on the next load
	// (grid → list direction). Called once on toolbar creation and on every
	// roci_media_folder model change.

	function rociUpdateListViewLink() {
		var listLink = document.querySelector( 'a.view-list' );
		if ( ! listLink || ! wp.media || ! wp.media.frame ) {
			return;
		}
		var folder = '';
		try {
			var content = wp.media.frame.content.get();
			if ( content && content.collection && content.collection.props ) {
				folder = content.collection.props.get( 'roci_media_folder' ) || '';
			}
		} catch ( e ) {
			return;
		}
		var url = new URL( listLink.href, window.location.origin );
		if ( folder ) {
			url.searchParams.set( 'roci_media_folder', folder );
		} else {
			url.searchParams.delete( 'roci_media_folder' );
		}
		listLink.href = url.toString();
	}


	// ── Shared folder filter builder ───────────────────────────────────────

	function buildFolderFilters() {
		var filters = {};

		filters[ '' ] = {
			text:  rociMediaFolders.allLabel,
			props: { roci_media_folder: '', roci_no_folder: '' }
		};

		_.each( rociMediaFolders.terms, function ( term ) {
			filters[ term.term_id ] = {
				text:  term.name,
				props: { roci_media_folder: term.term_id, roci_no_folder: '' }
			};
		} );

		return filters;
	}


	// ── RociMediaFolderFilter ──────────────────────────────────────────────
	//
	// Standalone AttachmentFilters subclass for folder filtering.
	// Inherits the parent's change→refetch handler — selecting a folder in
	// the dropdown applies the filter immediately (no separate Filter button).

	var RociMediaFolderFilter = media.view.AttachmentFilters.extend( {

		id: 'roci-media-folder-filter',

		initialize: function () {
			// Seed roci_media_folder from the URL so the dropdown reflects the
			// active filter when entering grid view from a filtered list-view URL
			// (list → grid). select() fires inside the parent initialize() below
			// and compares model props against each filter's props using strict
			// ===. buildFolderFilters() stores term_id as integer, so coerce the
			// string URL param to int before the comparison runs.
			//
			// No guard on the URL-param branch: WP pre-populates collection.props
			// with the raw URL string ('28') because roci_media_folder is a
			// registered taxonomy var. A truthy-guard would skip this and leave
			// the string in the model, causing '28' === 28 to fail in select().
			// Always overwrite with the integer when the URL param is present.
			var urlParams   = new URLSearchParams( window.location.search );
			var folderParam = urlParams.get( 'roci_media_folder' );
			if ( folderParam !== null ) {
				var parsed = parseInt( folderParam, 10 );
				this.model.set( 'roci_media_folder', isNaN( parsed ) ? folderParam : parsed, { silent: true } );
			} else if ( ! this.model.has( 'roci_media_folder' ) ) {
				this.model.set( 'roci_media_folder', '', { silent: true } );
			}
			if ( ! this.model.has( 'roci_no_folder' ) ) {
				this.model.set( 'roci_no_folder', '', { silent: true } );
			}
			media.view.AttachmentFilters.prototype.initialize.apply( this, arguments );
			activeFilter = this;
		},

		createFilters: function () {
			this.filters = buildFolderFilters();
		}
	} );


	// ── AttachmentsBrowser extension ───────────────────────────────────────
	//
	// Injects the folder filter after WP's type (-80) and date (-75) filters.

	var OriginalBrowser = media.view.AttachmentsBrowser;

	media.view.AttachmentsBrowser = OriginalBrowser.extend( {

		createToolbar: function () {
			OriginalBrowser.prototype.createToolbar.apply( this, arguments );

			// hasModal: true only on upload.php (rociAdminFolders is localised
			// there). False on post.php/post-new.php (media picker modal).
			var hasModal = typeof rociAdminFolders !== 'undefined';

			if ( rociMediaFolders.terms.length || hasModal ) {
				this.toolbar.set( 'rociMediaFolderFilter', new RociMediaFolderFilter( {
					controller: this.controller,
					model:      this.collection.props,
					priority:   -70
				} ).render() );
			}

			// Sync the .view-list toggle href with the active folder filter so
			// navigating grid → list preserves the selection (Fix B).
			rociUpdateListViewLink();
			this.collection.props.on( 'change:roci_media_folder', rociUpdateListViewLink );
		}
	} );


	// ── Sidebar filter event ───────────────────────────────────────────────
	//
	// folders-sidebar.js dispatches roci:sidebarFilter when the user clicks
	// a sidebar entry while in grid view. Set the Backbone model props to
	// trigger the AJAX refetch; clear the opposing prop to prevent conflicts.

	document.addEventListener( 'roci:sidebarFilter', function ( e ) {
		if ( ! activeFilter ) {
			return;
		}

		var termId = e.detail.termId;

		if ( termId === '__all__' ) {
			activeFilter.model.set( { roci_media_folder: '', roci_no_folder: '' } );
		} else if ( termId === '__unassigned__' ) {
			activeFilter.model.set( { roci_media_folder: '', roci_no_folder: 1 } );
		} else {
			activeFilter.model.set( {
				roci_media_folder: parseInt( termId, 10 ),
				roci_no_folder:    ''
			} );
		}
	} );


	// ── Filter select rebuild ──────────────────────────────────────────────
	//
	// Shared by roci:folderCreated (post-creation) and roci:folderOrderChanged
	// (post-reorder). Converts the server's {value, label} option format to
	// the {term_id, name} shape that buildFolderFilters() expects, then
	// re-renders the Backbone filter select.

	function rebuildFolderFilterSelect( options ) {
		rociMediaFolders.terms = options.map( function ( opt ) {
			return { term_id: opt.value, name: opt.label };
		} );

		if ( activeFilter ) {
			activeFilter.createFilters();
			activeFilter.render();
		}
	}

	document.addEventListener( 'roci:folderCreated', function ( e ) {
		rebuildFolderFilterSelect( e.detail.options );
	} );

	document.addEventListener( 'roci:folderOrderChanged', function ( e ) {
		rebuildFolderFilterSelect( e.detail.options );
	} );

}( wp.media ) );
