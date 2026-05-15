/* global rociUploadPicker */

/**
 * Folder upload picker — renders a <select> next to admin upload dropzones
 * and wires the selected folder term ID into Plupload's multipart_params.
 *
 * @package El_Rocinante
 * @version 2.8.6
 * Updated: 2026-05-15
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
    var labelText  = data.label      || 'Upload to folder';
    var helperText = data.helperText || 'Choose a folder before uploading. Leave blank for unassigned.';

    function escapeHtml( str ) {
        var div = document.createElement( 'div' );
        div.textContent = String( str );
        return div.innerHTML;
    }

    function buildPickerHTML() {
        var options = '<option value="">— No folder —</option>';
        folders.forEach( function ( f ) {
            options += '<option value="' + f.id + '">' + escapeHtml( f.name ) + '</option>';
        } );
        return '<div class="roci-upload-picker">' +
            '<label class="roci-upload-picker__label">' + escapeHtml( labelText ) + '</label>' +
            '<select class="roci-upload-picker__select">' + options + '</select>' +
            '<p class="roci-upload-picker__helper">' + escapeHtml( helperText ) + '</p>' +
            '</div>';
    }

    function injectPickers() {
        var selectors = [ '.uploader-inline', '#plupload-upload-ui', '.media-frame-uploader', '.uploader-window' ];
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

    function setupDropzoneObserver () {
        if ( window._rociObserverSetup ) {
            return;
        }
        var selectors = '.uploader-inline, #plupload-upload-ui, .media-frame-uploader, .uploader-window';
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
