/* global wp, rociMediaFolders, _ */

/**
 * Media Folder Filter — modal toolbar extension
 *
 * Injects a "Folder" <select> into the wp.media modal toolbar so editors
 * can filter the attachment grid by roci_media_folder term. Works in both
 * the Insert Media dialog and the Featured Image picker.
 *
 * How it works:
 *   1. MediaFolderFilter extends wp.media.view.AttachmentFilters, building
 *      its option list from the PHP-localized rociMediaFolders object.
 *   2. We extend wp.media.view.AttachmentsBrowser (the grid view container)
 *      and override createToolbar() to inject our filter alongside the
 *      built-in type and date filters.
 *   3. When a folder is selected, wp.media sets roci_media_folder on the
 *      collection props, which gets forwarded to the query-attachments AJAX
 *      request. The PHP ajax_query_attachments_args filter in
 *      inc/media-folders.php picks it up and applies the tax_query.
 *
 * Version: 1.0.0
 */

( function ( media ) {

    'use strict';

    // Bail if wp.media hasn't loaded (should never happen given our deps).
    if ( ! media || ! media.view ) {
        return;
    }

    /**
     * MediaFolderFilter
     *
     * A custom AttachmentFilters view populated from the localized term list.
     * Each option sets `roci_media_folder` on the collection props; the empty
     * string option clears the filter.
     */
    var MediaFolderFilter = media.view.AttachmentFilters.extend( {

        id: 'roci-media-folder-filter',

        createFilters: function () {
            var filters = {};

            // "All Folders" — clears any active folder filter.
            filters[ '' ] = {
                text:  rociMediaFolders.allLabel,
                props: { roci_media_folder: '' }
            };

            // One option per term; indentation is already baked into term.name by PHP.
            _.each( rociMediaFolders.terms, function ( term ) {
                filters[ term.term_id ] = {
                    text:  term.name,
                    props: { roci_media_folder: term.term_id }
                };
            } );

            this.filters = filters;
        }
    } );


    /**
     * Extend AttachmentsBrowser to inject the folder filter into the toolbar.
     *
     * createToolbar() is the standard hook point for adding custom filter
     * controls to the media modal. We call the original first so the built-in
     * filters (type, date) are already in place, then append ours.
     *
     * Priority -75 places it between the "Uploaded to this post" filter (-80)
     * and the media-type filter (-60).
     */
    var OriginalBrowser = media.view.AttachmentsBrowser;

    media.view.AttachmentsBrowser = OriginalBrowser.extend( {

        createToolbar: function () {
            // Build the standard toolbar first.
            OriginalBrowser.prototype.createToolbar.apply( this, arguments );

            // No terms yet — nothing to filter by, so don't render the control.
            if ( ! rociMediaFolders.terms || ! rociMediaFolders.terms.length ) {
                return;
            }

            this.toolbar.set( 'rociMediaFolderFilter', new MediaFolderFilter( {
                controller: this.controller,
                model:      this.collection.props,
                priority:   -75
            } ).render() );
        }
    } );

}( wp.media ) );
