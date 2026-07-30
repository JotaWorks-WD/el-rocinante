/* global rociUploadPicker */

/**
 * Folder upload picker — renders a <select> next to admin upload dropzones
 * and wires the selected folder term ID into Plupload's multipart_params.
 *
 * Also repopulates that <select> in place on roci:folderCreated, so a folder
 * created from the sidebar is immediately available as an upload destination
 * without a page reload. See rebuildPickers().
 *
 * @package El_Rocinante
 * @version 2.9.0
 * Updated: 2026-07-30
 */

( function () {

    'use strict';

    // XHR interceptor — catches all upload requests to async-upload.php
    // regardless of which uploader framework initiated them. Safety net
    // for paths the wp.Uploader prototype patch doesn't reach
    // (media-new.php, list view, any standalone Plupload init).
    ( function () {
        if ( window.XMLHttpRequest.prototype._rociPatched ) {
            return;
        }
        var origOpen = window.XMLHttpRequest.prototype.open;
        var origSend = window.XMLHttpRequest.prototype.send;
        window.XMLHttpRequest.prototype.open = function ( method, url ) {
            this._rociUrl = url;
            return origOpen.apply( this, arguments );
        };
        window.XMLHttpRequest.prototype.send = function ( body ) {
            if (
                this._rociUrl &&
                this._rociUrl.indexOf( 'async-upload.php' ) !== -1 &&
                body instanceof FormData &&
                ! body.has( 'roci_target_folder' )
            ) {
                var picker = document.querySelector( '.roci-upload-picker__select' );
                if ( picker && picker.value ) {
                    body.append( 'roci_target_folder', picker.value );
                }
            }
            return origSend.apply( this, arguments );
        };
        window.XMLHttpRequest.prototype._rociPatched = true;
    } )();

    var data       = window.rociUploadPicker || {};
    var folders    = data.folders    || [];
    var labelText  = data.label      || 'Upload to fauxlder';
    var helperText = data.helperText || 'Choose a fauxlder before uploading. Leave blank for unassigned.';

    function escapeHtml( str ) {
        var div = document.createElement( 'div' );
        div.textContent = String( str );
        return div.innerHTML;
    }

    // Option markup only, so first paint and the post-create rebuild below
    // cannot drift apart. Names arrive DECODED from PHP
    // (roci_get_upload_picker_folders in inc/folders/upload.php), so escapeHtml()
    // is the single correct encoder here — do not process them twice.
    function buildPickerOptionsHTML() {
        var options = '<option value="">— No fauxlder —</option>';
        folders.forEach( function ( f ) {
            options += '<option value="' + f.id + '">' + escapeHtml( f.name ) + '</option>';
        } );
        return options;
    }

    function buildPickerHTML() {
        return '<div class="roci-upload-picker">' +
            '<label class="roci-upload-picker__label">' + escapeHtml( labelText ) + '</label>' +
            '<select class="roci-upload-picker__select">' + buildPickerOptionsHTML() + '</select>' +
            '<p class="roci-upload-picker__helper">' + escapeHtml( helperText ) + '</p>' +
            '</div>';
    }

    /**
     * Repopulate every rendered picker from a fresh folder list.
     *
     * Rebuilds the <option> list IN PLACE rather than re-running injectPickers():
     * that function early-returns on any dropzone already holding a
     * .roci-upload-picker, so it would leave the stale <select> exactly as it
     * found it.
     *
     * `folders` is reassigned rather than window.rociUploadPicker.folders,
     * because the module captured a closure copy of that array at the top of
     * this IIFE and every builder reads the closure var, not the global.
     *
     * @param {Array} list  Flat [ { id, name } ] with names already decoded.
     */
    function rebuildPickers( list ) {
        if ( ! Array.isArray( list ) ) {
            return;
        }

        folders = list;

        var optionsHtml = buildPickerOptionsHTML();

        document.querySelectorAll( '.roci-upload-picker__select' ).forEach( function ( sel ) {
            var prevVal = sel.value;

            sel.innerHTML = optionsHtml;

            // Restore the prior selection only if it survived the rebuild.
            // Assigning a value with no matching option sets selectedIndex to -1
            // and renders the control blank, so the miss falls back to the
            // "— No fauxlder —" sentinel instead. Matched by iterating options
            // rather than a querySelector, so the stored value is never
            // interpolated into a selector.
            var hasPrev = false;
            if ( prevVal ) {
                for ( var i = 0; i < sel.options.length; i++ ) {
                    if ( sel.options[ i ].value === prevVal ) {
                        hasPrev = true;
                        break;
                    }
                }
            }
            sel.value = hasPrev ? prevVal : '';

            // Setting .value programmatically fires no change event, so on a
            // reset the uploaders would keep the old roci_target_folder and a
            // file would land somewhere the UI no longer shows as selected.
            // Unreachable on a create (the list only grows), cheap to hold.
            if ( ! hasPrev && prevVal ) {
                updateAllUploaders( 0 );
            }
        } );
    }

    function injectPickers() {
        var selectors = [ '.uploader-inline', '#plupload-upload-ui' ];
        selectors.forEach( function ( sel ) {
            document.querySelectorAll( sel ).forEach( function ( dz ) {
                if ( dz.querySelector( '.roci-upload-picker' ) ) return;
                var wrapper = document.createElement( 'div' );
                wrapper.innerHTML = buildPickerHTML();
                dz.insertBefore( wrapper.firstChild, dz.firstChild );
            } );
        } );
    }

    function updateAllUploaders( termId ) {
        if ( window._wpPluploadSettings && window._wpPluploadSettings.defaults ) {
            window._wpPluploadSettings.defaults.multipart_params = window._wpPluploadSettings.defaults.multipart_params || {};
            window._wpPluploadSettings.defaults.multipart_params.roci_target_folder = termId;
        }

        // Also update existing Plupload instances directly. Covers uploaders
        // created outside the wp.Uploader framework (media-new.php, list view
        // upload modal, any standalone Plupload init).
        if ( window.plupload && window.plupload.uploaders ) {
            Object.keys( window.plupload.uploaders ).forEach( function ( id ) {
                var up = window.plupload.uploaders[ id ];
                if ( up && typeof up.getOption === 'function' && typeof up.setOption === 'function' ) {
                    var current = up.getOption( 'multipart_params' ) || {};
                    current.roci_target_folder = termId;
                    up.setOption( 'multipart_params', current );
                }
            } );
        }
    }

    function patchUploader () {
        if ( ! window.wp || ! window.wp.Uploader || ! window.wp.Uploader.prototype ) {
            return false;
        }
        if ( window.wp.Uploader.prototype._rociPatched ) {
            return true;
        }
        var origInit = window.wp.Uploader.prototype.init;
        window.wp.Uploader.prototype.init = function () {
            var result = origInit.apply( this, arguments );
            var self = this;
            if ( self.uploader && typeof self.uploader.bind === 'function' ) {
                self.uploader.bind( 'BeforeUpload', function () {
                    var picker = document.querySelector( '.roci-upload-picker__select' );
                    var termId = ( picker && picker.value ) ? parseInt( picker.value, 10 ) : 0;
                    if ( termId ) {
                        var current = self.uploader.getOption( 'multipart_params' ) || {};
                        current.roci_target_folder = termId;
                        self.uploader.setOption( 'multipart_params', current );
                    }
                } );
            }
            return result;
        };
        window.wp.Uploader.prototype._rociPatched = true;
        return true;
    }

    function attachHandler() {
        document.addEventListener( 'change', function ( e ) {
            if ( ! e.target.classList || ! e.target.classList.contains( 'roci-upload-picker__select' ) ) return;
            var value  = e.target.value;
            var termId = value ? parseInt( value, 10 ) : 0;
            updateAllUploaders( termId );
            document.querySelectorAll( '.roci-upload-picker__select' ).forEach( function ( sel ) {
                if ( sel !== e.target ) sel.value = value;
            } );
        } );
    }

    // admin-folders.js dispatches roci:folderCreated after a successful create.
    // Its detail carries picker_folders — a flat decoded list built by
    // roci_get_upload_picker_folders() — for roci_media_folder creations only;
    // rebuildPickers() no-ops on the undefined key for page/post/CPT folders.
    function attachFolderCreatedListener () {
        document.addEventListener( 'roci:folderCreated', function ( e ) {
            rebuildPickers( e.detail && e.detail.picker_folders );
        } );
    }

    function setupDropzoneObserver () {
        if ( window._rociObserverSetup ) {
            return;
        }
        var selectors = '.uploader-inline, #plupload-upload-ui';
        var observer = new MutationObserver( function ( mutations ) {
            for ( var i = 0; i < mutations.length; i++ ) {
                var addedNodes = mutations[ i ].addedNodes;
                for ( var j = 0; j < addedNodes.length; j++ ) {
                    var node = addedNodes[ j ];
                    if ( node.nodeType !== 1 ) {
                        continue;
                    }
                    if ( ( node.matches && node.matches( selectors ) ) || ( node.querySelector && node.querySelector( selectors ) ) ) {
                        injectPickers();
                        return;
                    }
                }
            }
        } );
        observer.observe( document.body, { childList: true, subtree: true } );
        window._rociObserverSetup = true;
    }

    function init () {
        injectPickers();
        attachHandler();
        attachFolderCreatedListener();
        setupDropzoneObserver();

        if ( ! patchUploader() ) {
            var attempts = 0;
            var interval = setInterval( function () {
                attempts++;
                if ( patchUploader() || attempts > 50 ) {
                    clearInterval( interval );
                }
            }, 100 );
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
