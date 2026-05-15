/* global rociUploadPicker */

/**
 * Folder upload picker — renders a <select> next to admin upload dropzones
 * and wires the selected folder term ID into Plupload's multipart_params.
 *
 * @package El_Rocinante
 * @version 2.8.0
 * Updated: 2026-05-15
 */

( function () {

    'use strict';

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
        if ( window.wp && window.wp.Uploader && window.wp.Uploader.queue && window.wp.Uploader.queue.each ) {
            window.wp.Uploader.queue.each( function ( uploader ) {
                if ( uploader && uploader.uploader && uploader.uploader.setOption ) {
                    var current = uploader.uploader.getOption( 'multipart_params' ) || {};
                    current.roci_target_folder = termId;
                    uploader.uploader.setOption( 'multipart_params', current );
                }
            } );
        }
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

    function init() {
        injectPickers();
        attachHandler();
        document.body.addEventListener( 'click', function () {
            setTimeout( injectPickers, 150 );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
