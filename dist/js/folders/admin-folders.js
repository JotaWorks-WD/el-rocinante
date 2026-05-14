/* global rociAdminFolders */

/**
 * Admin Folder Manager — "+ New Folder" button and modal
 *
 * Powers the inline folder creation UI on:
 *   - Media Library admin list  (upload.php)
 *   - Pages admin list          (edit.php?post_type=page)
 *
 * On success the AJAX handler returns a rebuilt term list; this script
 * uses it to refresh both the filter dropdown and the modal parent
 * dropdown in place — no page reload needed.
 *
 * Version: 1.2.2
 * Updated: 2026-05-14
 */

( function () {

    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {

        var btn          = document.getElementById( 'roci-new-folder-btn' );
        var backdrop     = document.getElementById( 'roci-folder-backdrop' );
        var modal        = document.getElementById( 'roci-folder-modal' );
        var form         = document.getElementById( 'roci-folder-form' );
        var nameInput    = document.getElementById( 'roci-folder-name' );
        var parentSelect = document.getElementById( 'roci-folder-parent' );
        var errorEl      = document.getElementById( 'roci-folder-error' );

        // Guard: bail if the modal isn't in the DOM. The button may be absent
        // in grid view (it's injected by media-folder-filter.js there instead).
        if ( ! modal ) {
            return;
        }

        // ── Open ──────────────────────────────────────────────────────────

        // The list-view button (#roci-new-folder-btn) is rendered by PHP.
        // The grid-view button is a Backbone view in media-folder-filter.js
        // that calls openModal() directly — so this handler is list-view only.
        if ( btn ) {
            btn.addEventListener( 'click', function () {
                nameInput.value = '';
                hideError();
                modal.style.display    = 'block';
                backdrop.style.display = 'block';
                nameInput.focus();
            } );
        }

        // ── Close ─────────────────────────────────────────────────────────

        function closeModal() {
            modal.style.display    = 'none';
            backdrop.style.display = 'none';
        }

        document.getElementById( 'roci-folder-cancel' ).addEventListener( 'click', closeModal );

        // Clicking the backdrop closes the modal.
        backdrop.addEventListener( 'click', closeModal );

        // Esc key closes the modal.
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' && modal.style.display === 'block' ) {
                closeModal();
            }
        } );

        // ── Error display ─────────────────────────────────────────────────

        function showError( message ) {
            errorEl.textContent    = message;
            errorEl.style.display  = 'block';
        }

        function hideError() {
            errorEl.textContent   = '';
            errorEl.style.display = 'none';
        }

        // ── Rebuild a <select> from a flat options array ───────────────────
        //
        // The server returns the full updated term list after each creation
        // so both the filter dropdown and the modal parent dropdown stay in
        // sync without a page reload. The leading "All Folders" / "No Parent"
        // option is not included in the server array — we prepend it here.

        function rebuildSelect( selectEl, options, leadLabel, leadValue ) {
            if ( ! selectEl ) {
                return;
            }

            // Remember the current value so we can try to restore it.
            var prevVal = selectEl.value;

            selectEl.innerHTML = '';

            var lead   = document.createElement( 'option' );
            lead.value = leadValue;
            lead.text  = leadLabel;
            selectEl.appendChild( lead );

            options.forEach( function ( item ) {
                var opt   = document.createElement( 'option' );
                opt.value = item.value;
                opt.text  = item.label;
                selectEl.appendChild( opt );
            } );

            // Restore previous selection if it still exists in the new list.
            selectEl.value = prevVal;
        }

        // ── Submit ────────────────────────────────────────────────────────

        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            hideError();

            var name = nameInput.value.trim();
            if ( ! name ) {
                showError( rociAdminFolders.i18n.nameRequired );
                nameInput.focus();
                return;
            }

            var parent = parentSelect ? parentSelect.value : '0';

            // Disable submit and show a loading state while the request is in flight.
            var submitBtn     = form.querySelector( 'button[type="submit"]' );
            var originalLabel = submitBtn.textContent;
            submitBtn.disabled    = true;
            submitBtn.textContent = originalLabel + '…';

            var data = new FormData();
            data.append( 'action',      'roci_create_folder' );
            data.append( 'nonce',       rociAdminFolders.nonce );
            data.append( 'taxonomy',    rociAdminFolders.taxonomy );
            data.append( 'folder_name', name );
            data.append( 'parent',      parent );

            fetch( rociAdminFolders.ajaxUrl, {
                method:      'POST',
                credentials: 'same-origin',
                body:        data
            } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( response ) {
                    if ( ! response.success ) {
                        showError( response.data || rociAdminFolders.i18n.requestFailed );
                        return;
                    }

                    var options = response.data.options;

                    // Re-query the filter select at response time, not at page
                    // load. In grid view the Backbone select doesn't exist at
                    // DOMContentLoaded but is in the DOM by the time the user
                    // creates a folder. rebuildSelect is null-safe if the
                    // element still isn't found.
                    rebuildSelect(
                        document.getElementById( rociAdminFolders.filterSelectId ),
                        options,
                        rociAdminFolders.i18n.allFolders,
                        ''
                    );

                    // Refresh the modal parent dropdown.
                    rebuildSelect(
                        parentSelect,
                        options,
                        rociAdminFolders.i18n.noParent,
                        '0'
                    );

                    // Notify media-folder-filter.js so it can refresh the
                    // Backbone filter in grid view with the updated term list.
                    document.dispatchEvent( new CustomEvent( 'roci:folderCreated', {
                        detail: { options: options }
                    } ) );

                    closeModal();
                } )
                .catch( function () {
                    showError( rociAdminFolders.i18n.requestFailed );
                } )
                .finally( function () {
                    submitBtn.disabled    = false;
                    submitBtn.textContent = originalLabel;
                } );
        } );

    } );

} )();
