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
 * Bulk delete applies its own optimistic sidebar count decrements. It cannot
 * inherit the ones folders-sidebar.js performs, because that path is a patch on
 * wp.media.model.Attachment.prototype.destroy and this handler uses a
 * collection remove() — see performBulkDelete().
 *
 * The Move panel is position: fixed and openMoveDropdown() writes its top/left
 * inline on every open. That is what stops it being painted under the upload
 * dropzone — as an absolute panel its z-index was capped by an ancestor stacking
 * context in core's media-frame chain. The node stays in the action bar; it is
 * not portaled. See openMoveDropdown() and _admin-folders-bulk.scss:165.
 *
 * Note: the toast implementation is duplicated from folders-page-dragdrop.js.
 * Consolidation into a shared module is deferred to the audit phase (flagged).
 *
 * File:    dist/js/folders/folders-bulk.js
 * Version: 1.5.0
 * Updated: 2026-07-30
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
	var deleteBtnEl  = null;
	var selectAllBtn = null;
	var activeModal  = null;

	// Move panel geometry. GAP is the button-to-panel offset (was the `4` inside
	// the old top/bottom: calc(100% + 4px) in _admin-folders-bulk.scss); MARGIN
	// is the minimum inset kept from a viewport edge when the panel has to be
	// clamped because neither side has room.
	var MOVE_PANEL_GAP    = 4;
	var MOVE_PANEL_MARGIN = 4;

	// Single bound reference for the scroll/resize dismiss listeners, so open and
	// close attach and detach the same function object.
	var moveDismissHandler = null;

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

		// Delete permanently
		deleteBtnEl = document.createElement( 'button' );
		deleteBtnEl.type        = 'button';
		deleteBtnEl.className   = 'button roci-bulk-delete-btn';
		deleteBtnEl.textContent = rociFoldersBulk.i18n.deletePermanently;
		deleteBtnEl.disabled    = true; // enabled when items are selected (see updateActionBar)
		deleteBtnEl.addEventListener( 'click', onDeleteBtnClick );
		actionBar.appendChild( deleteBtnEl );

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

		// Inject before .attachments inside .attachments-browser.
		// Use attachmentsEl.parentNode instead of assuming browser is the direct parent.
		// WP media library DOM structure varies between WP versions, contexts (modal vs
		// full page), and plugin/theme wrappers. parentNode is always correct; browser
		// may not be.
		var browser       = document.querySelector( '.attachments-browser' );
		var attachmentsEl = browser && browser.querySelector( '.attachments' );
		if ( attachmentsEl && attachmentsEl.parentNode ) {
			attachmentsEl.parentNode.insertBefore( actionBar, attachmentsEl );
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

		if ( deleteBtnEl ) {
			deleteBtnEl.disabled = ( n === 0 );
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
			var n = selectedIds.size;
			header.textContent = n === 1
				? rociFoldersBulk.i18n.moveNItemsSingular
				: rociFoldersBulk.i18n.moveNItemsPlural.replace( '%d', n );
		}

		// Display FIRST: the panel has to be laid out before it can be measured,
		// and the direction decision below depends on its real height.
		moveDropdown.classList.add( 'roci-bulk-move-dropdown--open' );

		// The panel is position: fixed (see the long note at
		// _admin-folders-bulk.scss:165), so top/left are VIEWPORT-relative and
		// getBoundingClientRect() values are used directly with no scroll offset
		// added. That also makes window.innerHeight the correct reference frame
		// here — it was NOT correct while the panel was absolute inside the media
		// frame's own scroll region, which is the second defect this fixes.
		var btnRect     = moveBtn.getBoundingClientRect();
		var panelHeight = moveDropdown.offsetHeight;
		var panelWidth  = moveDropdown.offsetWidth;
		var spaceBelow  = window.innerHeight - btnRect.bottom;
		var spaceAbove  = btnRect.top;

		// Measured height, not the old hardcoded 320. That literal duplicated
		// max-height from the SCSS with nothing linking the two, and being the
		// MAXIMUM it also flipped short panels that would have fitted below.
		//
		// Prefer below; go above only when below cannot take the panel; and when
		// neither side can, take the roomier side rather than blindly flipping up
		// into a space just as inadequate — the third defect this fixes.
		var openAbove;
		if ( spaceBelow >= panelHeight + MOVE_PANEL_GAP ) {
			openAbove = false;
		} else if ( spaceAbove >= panelHeight + MOVE_PANEL_GAP ) {
			openAbove = true;
		} else {
			openAbove = spaceAbove > spaceBelow;
		}

		var top = openAbove
			? btnRect.top - panelHeight - MOVE_PANEL_GAP
			: btnRect.bottom + MOVE_PANEL_GAP;

		// Clamp into the viewport: in the neither-side-fits branch above, the
		// unclamped value runs off an edge. Max wins over min when the panel is
		// taller than the viewport, pinning it to the top rather than off-screen.
		top = Math.min( top, window.innerHeight - panelHeight - MOVE_PANEL_MARGIN );
		top = Math.max( MOVE_PANEL_MARGIN, top );

		// Right edge aligned to the button, which is what `right: 0` did while the
		// panel was absolute inside .roci-bulk-move-wrap. Clamped the same way.
		var left = btnRect.right - panelWidth;
		left = Math.min( left, window.innerWidth - panelWidth - MOVE_PANEL_MARGIN );
		left = Math.max( MOVE_PANEL_MARGIN, left );

		moveDropdown.style.top  = top + 'px';
		moveDropdown.style.left = left + 'px';

		// Retained as a state marker only — no CSS keys off it now that the inline
		// coordinate above is authoritative. Useful in devtools; do not give it
		// offsets again (see the SCSS note).
		if ( openAbove ) {
			moveDropdown.classList.add( 'roci-bulk-move-dropdown--above' );
		} else {
			moveDropdown.classList.remove( 'roci-bulk-move-dropdown--above' );
		}

		bindMoveDismiss();
	}

	// A fixed panel does not travel with its button, so any scroll or resize
	// invalidates the coordinates computed above. Dismissing on both is standard
	// dropdown behaviour and avoids a reposition-on-scroll loop.
	//
	// Scroll is CAPTURED (third arg true) because the media frame scrolls an inner
	// container rather than the window, and scroll events do not bubble from an
	// element up to window — a bubble-phase listener would never see them.
	function bindMoveDismiss() {
		if ( moveDismissHandler ) {
			return;
		}
		moveDismissHandler = function ( e ) {
			// Scrolling the panel's own folder list must not dismiss it:
			// .roci-bulk-move-list is overflow-y:auto and a capturing listener on
			// document sees its scroll events too.
			if ( e && 'scroll' === e.type && e.target && moveDropdown.contains( e.target ) ) {
				return;
			}
			closeMoveDropdown();
		};
		document.addEventListener( 'scroll', moveDismissHandler, true );
		window.addEventListener( 'resize', moveDismissHandler );
	}

	function unbindMoveDismiss() {
		if ( ! moveDismissHandler ) {
			return;
		}
		// The capture flag must match the one used to add, or the listener leaks.
		document.removeEventListener( 'scroll', moveDismissHandler, true );
		window.removeEventListener( 'resize', moveDismissHandler );
		moveDismissHandler = null;
	}

	function closeMoveDropdown() {
		if ( ! moveDropdown ) {
			return;
		}
		unbindMoveDismiss();
		moveDropdown.classList.remove( 'roci-bulk-move-dropdown--open', 'roci-bulk-move-dropdown--above' );
		// Clear the computed coordinates so a stale position cannot flash at the
		// old location before the next open recomputes them.
		moveDropdown.style.top  = '';
		moveDropdown.style.left = '';
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
				if ( typeof window.rociCancelAllReAddGuards === 'function' ) {
					window.rociCancelAllReAddGuards();
				}
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
				var undoneIds  = resp.data.moved || [];
				var undoTarget = targetTerm === '__unassigned__' ? '__unassigned__' : parseInt( targetTerm, 10 );
				undoneIds.forEach( function () {
					if ( typeof window.rociDecrementSidebarCount === 'function' ) {
						window.rociDecrementSidebarCount( undoTarget );
					}
				} );
				undoneIds.forEach( function ( id ) {
					var origins = previousAssignments[ String( id ) ];
					if ( ! origins || origins.length === 0 ) {
						if ( typeof window.rociIncrementSidebarCount === 'function' ) {
							window.rociIncrementSidebarCount( '__unassigned__' );
						}
					} else {
						origins.forEach( function ( termId ) {
							if ( typeof window.rociIncrementSidebarCount === 'function' ) {
								window.rociIncrementSidebarCount( parseInt( termId, 10 ) );
							}
						} );
					}
				} );
				refreshGrid();
				rociShowBulkToast( { message: rociFoldersBulk.i18n.undone, duration: 3000 } );
				return;
			}

			var movedIds     = resp.data.moved                || [];
			var prevAssign   = resp.data.previous_assignments || {};
			var resolvedName = resp.data.target_name          || targetName;

			// Guard against in-flight more() XHRs re-adding bulk-moved models.
			// Delegated to rociWatchForReAdd() shared helper (wp-media-refresh-shim.js).
			var guardSet    = new Set( movedIds.map( String ) );
			var bulkLibrary = null;
			if ( window.wp && wp.media && wp.media.frame ) {
				try {
					var guardState = wp.media.frame.state();
					bulkLibrary = guardState && guardState.get( 'library' );
				} catch ( _e ) {}
			}
			// Guard only in filtered views — updateAndRemoveFromGrid returns
			// early in All Files, so there are no removed models to protect.
			// Installing the guard in All Files view makes it fire against
			// _requery(true) repopulation when the user clicks the destination
			// folder, evicting photos that should now be visible there.
			if ( bulkLibrary && typeof window.rociWatchForReAdd === 'function' ) {
				var bulkProps        = bulkLibrary.props;
				var bulkCurrFolder   = bulkProps && bulkProps.get( 'roci_media_folder' );
				var bulkIsUnassigned = bulkProps && !! bulkProps.get( 'roci_no_folder' );
				if ( bulkCurrFolder || bulkIsUnassigned ) {
					window.rociWatchForReAdd( bulkLibrary, guardSet );
				}
			}

			movedIds.forEach( function ( id ) {
				updateAndRemoveFromGrid( String( id ), targetTerm );
			} );

			movedIds.forEach( function ( id ) {
				var prevTerms = prevAssign[ id ];
				if ( Array.isArray( prevTerms ) && prevTerms.length > 0 ) {
					prevTerms.forEach( function ( termId ) {
						if ( typeof window.rociDecrementSidebarCount === 'function' ) {
							window.rociDecrementSidebarCount( termId );
						}
					} );
				} else {
					if ( typeof window.rociDecrementSidebarCount === 'function' ) {
						window.rociDecrementSidebarCount( '__unassigned__' );
					}
				}
				if ( targetTerm === '__unassigned__' ) {
					if ( typeof window.rociIncrementSidebarCount === 'function' ) {
						window.rociIncrementSidebarCount( '__unassigned__' );
					}
				} else {
					if ( typeof window.rociIncrementSidebarCount === 'function' ) {
						window.rociIncrementSidebarCount( parseInt( targetTerm, 10 ) );
					}
				}
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
	// DELETE HANDLER + CONFIRMATION MODAL
	// ======================================================================

	function onDeleteBtnClick() {
		if ( selectedIds.size === 0 ) {
			return;
		}
		showDeleteModal( selectedIds.size );
	}

	function showDeleteModal( n ) {
		// Only one modal at a time.
		if ( activeModal ) {
			return;
		}

		var title = n === 1
			? rociFoldersBulk.i18n.deleteModalTitleSingular
			: rociFoldersBulk.i18n.deleteModalTitlePlural.replace( '%d', n );

		var backdrop = document.createElement( 'div' );
		backdrop.className = 'roci-modal-backdrop';

		var modal = document.createElement( 'div' );
		modal.className = 'roci-modal';
		modal.setAttribute( 'role',            'dialog' );
		modal.setAttribute( 'aria-modal',      'true' );
		modal.setAttribute( 'aria-labelledby', 'roci-modal-title' );

		var titleEl = document.createElement( 'h2' );
		titleEl.id          = 'roci-modal-title';
		titleEl.textContent = title;
		modal.appendChild( titleEl );

		var bodyEl = document.createElement( 'p' );
		bodyEl.textContent = rociFoldersBulk.i18n.deleteModalBody;
		modal.appendChild( bodyEl );

		var actions = document.createElement( 'div' );
		actions.className = 'roci-modal-actions';

		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type        = 'button';
		cancelBtn.className   = 'button roci-modal-cancel';
		cancelBtn.textContent = rociFoldersBulk.i18n.cancel;
		cancelBtn.addEventListener( 'click', dismissDeleteModal );
		actions.appendChild( cancelBtn );

		var confirmBtn = document.createElement( 'button' );
		confirmBtn.type        = 'button';
		confirmBtn.className   = 'button roci-modal-confirm roci-modal-destructive';
		confirmBtn.textContent = rociFoldersBulk.i18n.deleteConfirmLabel;
		confirmBtn.addEventListener( 'click', function () {
			dismissDeleteModal();
			var ids = [];
			selectedIds.forEach( function ( id ) { ids.push( id ); } );
			performBulkDelete( ids );
		} );
		actions.appendChild( confirmBtn );

		modal.appendChild( actions );
		backdrop.appendChild( modal );
		document.body.appendChild( backdrop );
		activeModal = backdrop;

		// Keyboard: ESC = dismiss; Tab = focus trap between cancel and confirm.
		backdrop.addEventListener( 'keydown', function ( e ) {
			var key = e.key || '';
			if ( key === 'Escape' || e.keyCode === 27 ) {
				dismissDeleteModal();
				return;
			}
			if ( key === 'Tab' || e.keyCode === 9 ) {
				e.preventDefault();
				var focused = document.activeElement;
				if ( e.shiftKey ) {
					( focused === cancelBtn ? confirmBtn : cancelBtn ).focus();
				} else {
					( focused === confirmBtn ? cancelBtn : confirmBtn ).focus();
				}
			}
		} );

		// Click on the backdrop (outside the modal box) = dismiss.
		backdrop.addEventListener( 'click', function ( e ) {
			if ( e.target === backdrop ) {
				dismissDeleteModal();
			}
		} );

		// Focus starts on Cancel — safer default for a destructive action.
		cancelBtn.focus();
	}

	function dismissDeleteModal() {
		if ( activeModal && activeModal.parentNode ) {
			activeModal.parentNode.removeChild( activeModal );
		}
		activeModal = null;
	}


	// ======================================================================
	// BULK AJAX DELETE
	// ======================================================================

	/**
	 * Read an attachment's roci_media_folder term IDs off its Backbone model.
	 *
	 * The array is put on every attachment model by roci_expose_attachment_folder()
	 * (inc/folders/taxonomies.php:143-148) via wp_prepare_attachment_for_js, for
	 * exactly this purpose.
	 *
	 * Returns an empty array when the attachment has no folder (i.e. it lives in
	 * Unassigned) AND when the model cannot be read at all. Callers treat both as
	 * "was unassigned" — matching the captured-vs-fallback semantics of
	 * handleAttachmentDeleted() in folders-sidebar.js. An un-hydrated model is
	 * therefore indistinguishable from a genuinely unassigned one; in the grid the
	 * models are always hydrated from query-attachments, so this does not arise in
	 * practice, and decrementSidebarCount() floors at zero if it ever did.
	 *
	 * @param  {number|string} id  Attachment post ID.
	 * @return {number[]}          Folder term IDs, possibly empty.
	 */
	function captureAttachmentFolders( id ) {
		var terms = [];
		try {
			if ( ! window.wp || ! wp.media || typeof wp.media.attachment !== 'function' ) {
				return terms;
			}
			var model = wp.media.attachment( String( id ) );
			var value = model ? model.get( 'roci_media_folder' ) : null;
			if ( Array.isArray( value ) ) {
				value.forEach( function ( termId ) {
					var parsed = parseInt( termId, 10 );
					if ( parsed ) {
						terms.push( parsed );
					}
				} );
			}
		} catch ( e ) {}
		return terms;
	}

	function performBulkDelete( ids ) {

		// Capture folder membership BEFORE the request goes out. The response
		// carries no term data (roci_ajax_bulk_delete_attachments returns only
		// deleted/failed), and by the time onload runs the models are about to be
		// detached from the library — so the counts have to be resolved up front.
		// Mirrors the pre-mutation capture in folders-sidebar.js:427-433.
		var capturedFolders = {};
		ids.forEach( function ( id ) {
			capturedFolders[ String( id ) ] = captureAttachmentFolders( id );
		} );

		var fd = new FormData();
		fd.append( 'action', 'roci_bulk_delete_attachments' );
		fd.append( 'nonce',  rociFoldersBulk.nonce );
		ids.forEach( function ( id ) {
			fd.append( 'attachment_ids[]', id );
		} );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociFoldersBulk.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status < 200 || xhr.status >= 300 ) {
				console.error( '[roci-bulk] Delete failed: HTTP', xhr.status );
				showDeleteToast( false, ids.length );
				return;
			}
			var resp;
			try { resp = JSON.parse( xhr.responseText ); } catch ( err ) { resp = null; }
			if ( ! resp || ! resp.success ) {
				console.error( '[roci-bulk] Delete failed:', ( resp && resp.data ) ? resp.data : 'Unknown error' );
				showDeleteToast( false, ids.length );
				return;
			}

			var deletedIds = resp.data.deleted || [];

			// ── Optimistic sidebar count decrements ────────────────────────
			//
			// This handler removes models with library.remove() below — a
			// Backbone COLLECTION remove, which fires no 'destroy'. The
			// Attachment.prototype.destroy patch in folders-sidebar.js:417-449
			// (and the handleAttachmentDeleted() decrements it wraps at :451)
			// therefore never runs on this path, and every count stayed at its
			// page-load value until a manual reload. Same shape as the bulk-move
			// decrements at :559-581, plus the All Files decrement a move
			// correctly omits — a move does not change the total, a delete does.
			//
			// Keyed off deletedIds, NOT ids: a partial failure must not
			// decrement a file that survived.
			deletedIds.forEach( function ( id ) {

				if ( typeof window.rociDecrementSidebarCount === 'function' ) {
					window.rociDecrementSidebarCount( '__all__' );
				}

				var terms = capturedFolders[ String( id ) ] || [];

				if ( terms.length > 0 ) {
					terms.forEach( function ( termId ) {
						if ( typeof window.rociDecrementSidebarCount === 'function' ) {
							window.rociDecrementSidebarCount( termId );
						}
					} );
				} else {
					if ( typeof window.rociDecrementSidebarCount === 'function' ) {
						window.rociDecrementSidebarCount( '__unassigned__' );
					}
				}
			} );

			if ( window.wp && wp.media && wp.media.frame ) {
				try {
					var delState   = wp.media.frame.state();
					var delLibrary = delState && delState.get( 'library' );
					if ( delLibrary ) {
						deletedIds.forEach( function ( id ) {
							var model = wp.media.attachment( String( id ) );
							if ( model ) {
								delLibrary.remove( model );
							}
						} );
					}
				} catch ( _e ) {}
			}

			exitSelectionMode();
			showDeleteToast( true, deletedIds.length );
		};

		xhr.onerror = function () {
			console.error( '[roci-bulk] Delete failed: network error' );
			showDeleteToast( false, ids.length );
		};

		xhr.send( fd );
	}

	function showDeleteToast( success, n ) {
		var msg;
		if ( success ) {
			msg = n === 1
				? rociFoldersBulk.i18n.deleteSuccessToastSingular
				: rociFoldersBulk.i18n.deleteSuccessToastPlural.replace( '%d', n );
		} else {
			msg = n === 1
				? rociFoldersBulk.i18n.deleteFailureToastSingular
				: rociFoldersBulk.i18n.deleteFailureToastPlural.replace( '%d', n );
		}
		rociShowBulkToast( { message: msg, duration: success ? 5000 : 8000 } );
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

				// -65, not -70. Core occupies -80 (type filter), -75 (date filter)
				// and -70 (its own Bulk Select toggle), and media-folder-filter.js
				// claims -70 for the Fauxlder filter. Equal priorities are not
				// ordered by anything declared — PriorityList.set() inserts before
				// the first view of strictly greater priority, so a tie resolves by
				// which script wrapped createToolbar last, i.e. by enqueue order.
				// -65 puts this button to the right of the filter, as intended, and
				// stays negative so it remains in the secondary toolbar list.
				this.toolbar.set( 'rociBulkOrganize', new BulkOrgView( { priority: -65 } ).render() );
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
