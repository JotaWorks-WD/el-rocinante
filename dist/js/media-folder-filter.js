/* global wp, rociMediaFolders, rociAdminFolders, _ */

/**
 * Media Folder Filter — grid view and modal toolbar extension
 *
 * Injects a folder <select> filter and, on upload.php, a "+ New Folder"
 * button into the wp.media toolbar. Works in three contexts:
 *   - upload.php grid view (standalone media library)
 *   - Post editor "Insert Media" dialog
 *   - Post editor "Featured Image" picker
 *
 * How filtering works:
 *   When the user selects a folder, wp.media sets roci_media_folder on
 *   the collection props. This prop travels through the query-attachments
 *   AJAX request. The PHP ajax_query_attachments_args filter in
 *   inc/media-folders.php picks it up and applies the tax_query.
 *
 * How the grid view button works:
 *   The button is injected only when rociAdminFolders is defined (upload.php).
 *   It triggers the modal managed by admin-folders.js. After creation,
 *   admin-folders.js dispatches roci:folderCreated; this script listens and
 *   re-renders the filter with the updated term list.
 *
 * Version: 1.1.0
 */

( function ( media ) {

    'use strict';

    if ( ! media || ! media.view ) {
        return;
    }

    // Holds the most recently created MediaFolderFilter instance so the
    // roci:folderCreated event handler can re-render it after term creation.
    var activeFilter = null;

    // True only on upload.php, where rociAdminFolders is localized and the
    // modal HTML is rendered by PHP (admin_footer hook).
    var hasModal = typeof rociAdminFolders !== 'undefined';


    // ── Modal open helper ──────────────────────────────────────────────────
    //
    // Called by the grid view button. The modal is rendered by PHP and
    // wired up by admin-folders.js — we just need to show it and reset state.

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


    // ── MediaFolderFilter ──────────────────────────────────────────────────

    var MediaFolderFilter = media.view.AttachmentFilters.extend( {

        id: 'roci-media-folder-filter',

        initialize: function () {
            media.view.AttachmentFilters.prototype.initialize.apply( this, arguments );
            // Keep a module-level reference so the event listener below can
            // call render() on the live instance after new folders are created.
            activeFilter = this;
        },

        createFilters: function () {
            var filters = {};

            // "All Folders" — clears any active folder filter.
            filters[ '' ] = {
                text:  rociMediaFolders.allLabel,
                props: { roci_media_folder: '' }
            };

            // One option per term; indentation is already baked in by PHP.
            _.each( rociMediaFolders.terms, function ( term ) {
                filters[ term.term_id ] = {
                    text:  term.name,
                    props: { roci_media_folder: term.term_id }
                };
            } );

            this.filters = filters;
        }
    } );


    // ── NewFolderButton ────────────────────────────────────────────────────
    //
    // A minimal Backbone view that renders a <button> in the media toolbar.
    // Only added when hasModal is true (upload.php), because the modal HTML
    // is only rendered on that screen.

    var NewFolderButton = media.View.extend( {
        tagName:    'button',
        className:  'button',
        attributes: { type: 'button' },

        initialize: function () {
            this.el.textContent      = rociAdminFolders.i18n.newFolderLabel;
            this.el.style.marginLeft = '4px';
            this.el.addEventListener( 'click', openModal );
        },

        render: function () {
            return this;
        }
    } );


    // ── AttachmentsBrowser extension ───────────────────────────────────────
    //
    // createToolbar() is the standard hook point for injecting custom controls
    // into the media modal/grid toolbar. We call the original first so built-in
    // filters (type, date) are already in place, then append ours.

    var OriginalBrowser = media.view.AttachmentsBrowser;

    media.view.AttachmentsBrowser = OriginalBrowser.extend( {

        createToolbar: function () {
            OriginalBrowser.prototype.createToolbar.apply( this, arguments );

            // On upload.php, always render the filter (even when empty) so that
            // activeFilter is set and can respond to roci:folderCreated before
            // the first folder is created. In the post editor modal, skip it when
            // there are no terms to avoid showing a pointless dropdown.
            if ( ( rociMediaFolders.terms && rociMediaFolders.terms.length ) || hasModal ) {
                this.toolbar.set( 'rociMediaFolderFilter', new MediaFolderFilter( {
                    controller: this.controller,
                    model:      this.collection.props,
                    priority:   -75
                } ).render() );
            }

            // Button only on upload.php — post editor modal has no modal HTML.
            if ( hasModal ) {
                this.toolbar.set( 'rociNewFolderBtn', new NewFolderButton( {
                    priority: -74
                } ).render() );
            }
        }
    } );


    // ── Post-creation refresh ──────────────────────────────────────────────
    //
    // admin-folders.js dispatches roci:folderCreated after a successful AJAX
    // creation. The options payload uses {value, label} format (from PHP's
    // roci_build_folder_options_for_select); rociMediaFolders.terms expects
    // {term_id, name} — convert before storing, then re-render the filter.

    document.addEventListener( 'roci:folderCreated', function ( e ) {
        rociMediaFolders.terms = e.detail.options.map( function ( opt ) {
            return { term_id: opt.value, name: opt.label };
        } );

        if ( activeFilter ) {
            activeFilter.render();
        }
    } );

}( wp.media ) );
