/* global wp, rociFoldersBulk */

/**
 * Folders Bulk Organize — selection mode, action bar, Move dropdown, bulk AJAX
 *
 * Replaces bulk-organize-button.js. Extends wp.media.view.AttachmentsBrowser
 * so the "Bulk Organize" toolbar button enters selection mode. While active:
 *   - An action bar replaces the standard media toolbar.
 *   - Each .attachment in the grid is click-selectable; a checkmark overlay
 *     and selection ring appear on selected items.
 *   - [Select All] / [Deselect All] toggle the full visible grid.
 *   - [Move ▼] opens an inline panel with the full folder tree.
 *   - [Delete Permanently] and [Download] are disabled placeholders.
 *   - [Cancel] restores the standard toolbar and clears all selections.
 *
 * On a successful bulk move the action bar is dismissed, a toast fires with
 * an [Undo] button, and moved attachments are surgically removed from the
 * current Backbone library view (mirrors v2.9.6 single-move behaviour).
 *
 * Note: the toast implementation is duplicated from folders-page-dragdrop.js.
 * Consolidation into a shared module is deferred to the audit phase (flagged).
 *
 * File:    dist/js/folders/folders-bulk.js
 * Version: 1.0.0
 * Updated: 2026-05-20
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	if ( window.location.pathname.indexOf( 'upload.php' ) === -1 ) {
		return;
	}

	if ( typeof rociFoldersBulk === 'undefined' ) {
		return;
	}


	// ======================================================================
	// STATE
	// ======================================================================

	var selectedIds  = new Set();
	var inSelectMode = false;
	var actionBar    = null;
	var moveDropdown = null;
	var countEl      = null;
	var moveBtn      = null;
	var selectAllBtn = null;

	// Exposed so folders-dragdrop.js can gate drag initiation.
	window.rociIsBulkSelectMode = function () {
		return inSelectMode;
	};


	// ======================================================================
	// SELECTION MODE
	// ======================================================================

	function enterSelectionMode() {
		inSelectMode = true;
		selectedIds  = new Set();
		document.body.classList.add( 'roci-bulk-mode' );
		buildActionBar();
		updateActionBar();
	}

	function exitSelectionMode() {
		inSelectMode = false;
		selectedIds  = new Set();
		closeMoveDropdown();
		document.body.classList.remove( 'roci-bulk-mode' );
		document.querySelectorAll( '.attachment.roci-is-selected' ).forEach( function ( el ) {
			el.classList.remove( 'roci-is-selected' );
			el.removeAttribute( 'aria-checked' );
		} );
		updateActionBar();
	}


	// ======================================================================
	// ACTION BAR
	// ======================================================================

	function buildActionBar() {
		if ( actionBar ) {
			return; // already built; CSS class on body controls visibility
		}

		actionBar = document.createElement( 'div' );
		actionBar.className = 'roci-bulk-action-bar';
		actionBar.setAttribute( 'role', 'toolbar' );
		actionBar.setAttribute( 'aria-label', 'Bulk organise actions' );

		// Count label
		countEl = document.createElement( 'span' );
		countEl.className   = 'roci-bulk-count';
		countEl.textContent = rociFoldersBulk.i18n.countZero;
		actionBar.appendChild( countEl );

		// Select All
		selectAllBtn = document.createElement( 'button' );
		selectAllBtn.type        = 'button';
		selectAllBtn.className   = 'button roci-bulk-select-all-btn';
		selectAllBtn.textContent = rociFoldersBulk.i18n.selectAll;
		selectAllBtn.addEventListener( 'click', onSelectAllClick );
		actionBar.appendChild( selectAllBtn );

		// Move button + dropdown wrapper
		var moveWrap = document.createElement( 'div' );
		moveWrap.className = 'roci-bulk-move-wrap';

		moveBtn = document.createElement( 'button' );
		moveBtn.type        = 'button';
		moveBtn.className   = 'button button-primary roci-bulk-move-btn';
		moveBtn.textContent = rociFoldersBulk.i18n.move;
		moveBtn.disabled    = true;
		moveBtn.addEventListener( 'click', onMoveBtnClick );
		moveWrap.appendChild( moveBtn );

		moveDropdown = buildMoveDropdown();
		moveWrap.appendChild( moveDropdown );

		actionBar.appendChild( moveWrap );

		// Delete permanently (disabled placeholder)
		var deleteBtn = document.createElement( 'button' );
		deleteBtn.type        = 'button';
		deleteBtn.className   = 'button roci-bulk-delete-btn';
		deleteBtn.textContent = rociFoldersBulk.i18n.deletePermanently;
		deleteBtn.disabled    = true;
		deleteBtn.title       = rociFoldersBulk.i18n.comingSoon;
		actionBar.appendChild( deleteBtn );

		// Download (disabled placeholder)
		var downloadBtn = document.createElement( 'button' );
		downloadBtn.type        = 'button';
		downloadBtn.className   = 'button roci-bulk-download-btn';
		downloadBtn.textContent = rociFoldersBulk.i18n.download;
		downloadBtn.disabled    = true;
		downloadBtn.title       = rociFoldersBulk.i18n.comingSoon;
		actionBar.appendChild( downloadBtn );

		// Cancel
		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type        = 'button';
		cancelBtn.className   = 'button roci-bulk-cancel-btn';
		cancelBtn.textContent = rociFoldersBulk.i18n.cancel;
		cancelBtn.addEventListener( 'click', exitSelectionMode );
		actionBar.appendChild( cancelBtn );

		// Inject before .attachments inside .attachments-browser
		var browser       = document.querySelector( '.attachments-browser' );
		var attachmentsEl = browser && browser.querySelector( '.attachments' );
		if ( browser && attachmentsEl ) {
			browser.insertBefore( actionBar, attachmentsEl );
		} else if ( browser ) {
			browser.appendChild( actionBar );
		} else {
			document.body.appendChild( actionBar );
		}
	}

	function updateActionBar() {
		if ( ! actionBar ) {
			return;
		}

		var n = selectedIds.size;

		if ( countEl ) {
			countEl.textContent = n === 0
				? rociFoldersBulk.i18n.countZero
				: rociFoldersBulk.i18n.countN.replace( '%d', n );
		}

		if ( moveBtn ) {
			moveBtn.disabled = ( n === 0 );
		}

		if ( selectAllBtn ) {
			var allAttachments = document.querySelectorAll( '.attachments .attachment' );
			var allSelected    = allAttachments.length > 0 && n === allAttachments.length;
			selectAllBtn.textContent = allSelected
				? rociFoldersBulk.i18n.deselectAll
				: rociFoldersBulk.i18n.selectAll;
		}
	}


	// ======================================================================
	// MOVE DROPDOWN
	// ======================================================================

	function buildMoveDropdown() {
		var dropdown = document.createElement( 'div' );
		dropdown.className = 'roci-bulk-move-dropdown';
		// Visible only when roci-bulk-move-dropdown--open class is present.

		var header = document.createElement( 'div' );
		header.className   = 'roci-bulk-move-header';
		header.textContent = '';
		dropdown.appendChild( header );

		var list = document.createElement( 'ul' );
		list.className = 'roci-bulk-move-list';

		// Unassigned entry
		var liUnassigned = makeMoveItem( '__unassigned__', rociFoldersBulk.i18n.unassigned, 0, 'dashicons-portfolio' );
		list.appendChild( liUnassigned );

		// Folder entries
		var terms = rociFoldersBulk.terms || [];
		terms.forEach( function ( term ) {
			var li = makeMoveItem( String( term.term_id ), term.name, term.depth, 'dashicons-category' );
			list.appendChild( li );
		} );

		dropdown.appendChild( list );
		return dropdown;
	}

	function makeMoveItem( termValue, name, depth, iconClass ) {
		var li = document.createElement( 'li' );
		li.className = 'roci-bulk-move-item';
		li.dataset.term = termValue;
		li.style.paddingLeft = ( 12 + depth * 16 ) + 'px';

		var icon = document.createElement( 'span' );
		icon.className  = 'dashicons ' + iconClass;
		icon.setAttribute( 'aria-hidden', 'true' );
		li.appendChild( icon );

		var label = document.createElement( 'span' );
		label.className   = 'roci-bulk-move-name';
		label.textContent = name;
		li.appendChild( label );

		li.addEventListener( 'click', function () {
			onMoveTargetClick( termValue, name );
		} );

		return li;
	}

	function openMoveDropdown() {
		if ( ! moveDropdown || ! moveBtn ) {
			return;
		}

		var header = moveDropdown.querySelector( '.roci-bulk-move-header' );
		if ( header ) {
			header.textContent = rociFoldersBulk.i18n.moveNItems.replace( '%d', selectedIds.size );
		}

		moveDropdown.classList.add( 'roci-bulk-move-dropdown--open' );

		// Flip above if insufficient space below
		var btnRect    = moveBtn.getBoundingClientRect();
		var spaceBelow = window.innerHeight - btnRect.bottom;
		if ( spaceBelow < 320 ) {
			moveDropdown.classList.add( 'roci-bulk-move-dropdown--above' );
		} else {
			moveDropdown.classList.remove( 'roci-bulk-move-dropdown--above' );
		}
	}

	function closeMoveDropdown() {
		if ( ! moveDropdown ) {
			return;
		}
		moveDropdown.classList.remove( 'roci-bulk-move-dropdown--open', 'roci-bulk-move-dropdown--above' );
	}


	// ======================================================================
	// SELECTION TOGGLE
	// ======================================================================

	function toggleAttachment( attachmentEl ) {
		var id = attachmentEl.dataset.id;
		if ( ! id ) {
			return;
		}
		if ( selectedIds.has( id ) ) {
			selectedIds.delete( id );
			attachmentEl.classList.remove( 'roci-is-selected' );
			attachmentEl.setAttribute( 'aria-checked', 'false' );
		} else {
			selectedIds.add( id );
			attachmentEl.classList.add( 'roci-is-selected' );
			attachmentEl.setAttribute( 'aria-checked', 'true' );
		}
		updateActionBar();
	}


	// ======================================================================
	// EVENT LISTENERS
	// ======================================================================

	// Intercept .attachment clicks in selection mode (capture phase so it
	// fires before Backbone's delegated click handlers open the detail panel).
	document.addEventListener( 'click', function ( e ) {
		if ( ! inSelectMode ) {
			return;
		}
		var attachment = e.target.closest( '.attachment' );
		if ( ! attachment ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		toggleAttachment( attachment );
	}, true );

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! inSelectMode ) {
			return;
		}
		var key = e.key || '';
		if ( key === ' ' || e.keyCode === 32 ) {
			var focused = document.activeElement && document.activeElement.closest( '.attachment' );
			if ( focused ) {
				e.preventDefault();
				toggleAttachment( focused );
			}
		}
		if ( key === 'Escape' || e.keyCode === 27 ) {
			if ( moveDropdown && moveDropdown.classList.contains( 'roci-bulk-move-dropdown--open' ) ) {
				closeMoveDropdown();
			} else {
				exitSelectionMode();
			}
		}
	} );

	// Close dropdown on outside click.
	document.addEventListener( 'click', function ( e ) {
		if ( ! moveDropdown || ! moveDropdown.classList.contains( 'roci-bulk-move-dropdown--open' ) ) {
			return;
		}
		if ( ! moveDropdown.contains( e.target ) && e.target !== moveBtn ) {
			closeMoveDropdown();
		}
	} );

	function onSelectAllClick() {
		var allAttachments = document.querySelectorAll( '.attachments .attachment' );
		var allSelected    = allAttachments.length > 0 && selectedIds.size === allAttachments.length;

		allAttachments.forEach( function ( el ) {
			var id = el.dataset.id;
			if ( ! id ) {
				return;
			}
			if ( allSelected ) {
				selectedIds.delete( id );
				el.classList.remove( 'roci-is-selected' );
				el.setAttribute( 'aria-checked', 'false' );
			} else {
				selectedIds.add( id );
				el.classList.add( 'roci-is-selected' );
				el.setAttribute( 'aria-checked', 'true' );
			}
		} );
		updateActionBar();
	}

	function onMoveBtnClick() {
		if ( selectedIds.size === 0 ) {
			return;
		}
		if ( moveDropdown && moveDropdown.classList.contains( 'roci-bulk-move-dropdown--open' ) ) {
			closeMoveDropdown();
		} else {
			openMoveDropdown();
		}
	}

	function onMoveTargetClick( targetTerm, targetName ) {
		closeMoveDropdown();
		if ( selectedIds.size === 0 ) {
			return;
		}
		var ids = [];
		selectedIds.forEach( function ( id ) { ids.push( id ); } );
		performBulkMove( ids, targetTerm, targetName, false, null );
	}


	// ======================================================================
	// BACKBONE GRID UPDATE
	// ======================================================================

	function updateAndRemoveFromGrid( attachmentId, targetTerm ) {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( ! library ) {
				return;
			}

			var newTerms = ( targetTerm === '__unassigned__' )
				? []
				: [ parseInt( targetTerm, 10 ) ];

			var model = wp.media.attachment( attachmentId );
			if ( model ) {
				model.set( 'roci_media_folder', newTerms );
			}

			var props        = library.props;
			var currFolder   = props && props.get( 'roci_media_folder' );
			var isUnassigned = props && !! props.get( 'roci_no_folder' );

			if ( ! currFolder && ! isUnassigned ) {
				return; // All Files view — attachment stays visible
			}

			if ( model ) {
				library.remove( model );
			}
		} catch ( e ) {}
	}

	function refreshGrid() {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( library && typeof library._requery === 'function' ) {
				library._requery( true );
			}
		} catch ( e ) {}
	}


	// ======================================================================
	// BULK AJAX MOVE
	// ======================================================================

	function performBulkMove( ids, targetTerm, targetName, isUndo, previousAssignments ) {

		var fd = new FormData();
		fd.append( 'nonce', rociFoldersBulk.nonce );

		if ( isUndo ) {
			fd.append( 'action',      'roci_bulk_undo_move_attachments' );
			fd.append( 'assignments', JSON.stringify( previousAssignments ) );
		} else {
			fd.append( 'action',      'roci_bulk_move_attachments' );
			fd.append( 'target_term', targetTerm );
			ids.forEach( function ( id ) {
				fd.append( 'attachment_ids[]', id );
			} );
		}

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociFoldersBulk.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status < 200 || xhr.status >= 300 ) {
				console.error( '[roci-bulk] Move failed: HTTP', xhr.status );
				return;
			}
			var resp;
			try { resp = JSON.parse( xhr.responseText ); } catch ( err ) { resp = null; }
			if ( ! resp || ! resp.success ) {
				console.error( '[roci-bulk] Move failed:', ( resp && resp.data ) ? resp.data : 'Unknown error' );
				return;
			}

			if ( isUndo ) {
				refreshGrid();
				rociShowBulkToast( { message: rociFoldersBulk.i18n.undone, duration: 3000 } );
				return;
			}

			var movedIds    = resp.data.moved             || [];
			var prevAssign  = resp.data.previous_assignments || {};
			var resolvedName = resp.data.target_name || targetName;

			movedIds.forEach( function ( id ) {
				updateAndRemoveFromGrid( String( id ), targetTerm );
			} );

			exitSelectionMode();

			var n   = movedIds.length;
			var msg = ( targetTerm === '__unassigned__' )
				? rociFoldersBulk.i18n.movedUnassigned.replace( '%d', n )
				: rociFoldersBulk.i18n.moved.replace( '%d', n ).replace( '%s', resolvedName );

			rociShowBulkToast( {
				message:      msg,
				undoCallback: function () {
					performBulkMove( movedIds.map( String ), targetTerm, resolvedName, true, prevAssign );
				}
			} );
		};

		xhr.onerror = function () {
			console.error( '[roci-bulk] Move failed: network error' );
		};

		xhr.send( fd );
	}


	// ======================================================================
	// TOAST  (duplicated from folders-page-dragdrop.js — consolidate in audit)
	// ======================================================================

	var currentToast   = null;
	var currentTimeout = null;

	function rociShowBulkToast( opts ) {
		if ( currentToast && currentToast.parentNode ) {
			currentToast.parentNode.removeChild( currentToast );
		}
		if ( currentTimeout ) {
			clearTimeout( currentTimeout );
			currentTimeout = null;
		}

		var toast = document.createElement( 'div' );
		toast.className = 'roci-toast';
		toast.setAttribute( 'role', 'alert' );
		toast.setAttribute( 'aria-live', 'polite' );

		var msgEl = document.createElement( 'span' );
		msgEl.className   = 'roci-toast__message';
		msgEl.textContent = opts.message;
		toast.appendChild( msgEl );

		if ( opts.undoCallback ) {
			var undoBtn = document.createElement( 'button' );
			undoBtn.type        = 'button';
			undoBtn.className   = 'roci-toast__undo';
			undoBtn.textContent = rociFoldersBulk.i18n.undo;
			undoBtn.addEventListener( 'click', function () {
				dismiss();
				opts.undoCallback();
			} );
			toast.appendChild( undoBtn );
		}

		var closeBtn = document.createElement( 'button' );
		closeBtn.type      = 'button';
		closeBtn.className = 'roci-toast__close';
		closeBtn.setAttribute( 'aria-label', 'Dismiss' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', dismiss );
		toast.appendChild( closeBtn );

		function dismiss() {
			if ( currentTimeout ) {
				clearTimeout( currentTimeout );
				currentTimeout = null;
			}
			toast.classList.remove( 'roci-toast--visible' );
			toast.classList.add( 'roci-toast--hiding' );
			setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
				if ( currentToast === toast ) {
					currentToast = null;
				}
			}, 220 );
		}

		document.body.appendChild( toast );
		currentToast = toast;

		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				toast.classList.add( 'roci-toast--visible' );
			} );
		} );

		currentTimeout = setTimeout( dismiss, opts.duration || 8000 );
	}


	// ======================================================================
	// ATTACHMENTSBROWSER EXTENSION
	// ======================================================================

	function patchAttachmentsBrowser() {
		if ( ! window.wp || ! wp.media || ! wp.media.view || ! wp.media.view.AttachmentsBrowser ) {
			return false;
		}
		if ( wp.media.view.AttachmentsBrowser.prototype._rociBulkPatched ) {
			return true;
		}

		var OrigBrowser = wp.media.view.AttachmentsBrowser;

		wp.media.view.AttachmentsBrowser = OrigBrowser.extend( {
			createToolbar: function () {
				OrigBrowser.prototype.createToolbar.apply( this, arguments );

				var BulkOrgView = wp.media.View.extend( {
					tagName:   'button',
					className: 'button roci-bulk-organize-btn',
					render: function () {
						this.el.setAttribute( 'type', 'button' );
						this.el.textContent = rociFoldersBulk.i18n.bulkOrganize;
						this.el.addEventListener( 'click', enterSelectionMode );
						return this;
					}
				} );

				this.toolbar.set( 'rociBulkOrganize', new BulkOrgView( { priority: -70 } ).render() );
			}
		} );

		wp.media.view.AttachmentsBrowser.prototype._rociBulkPatched = true;
		return true;
	}

	( function () {
		if ( patchAttachmentsBrowser() ) {
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( patchAttachmentsBrowser() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();

} )();
