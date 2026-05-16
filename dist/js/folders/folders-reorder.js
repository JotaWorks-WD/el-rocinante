/* global rociFoldersReorder */

/**
 * Folders Reorder — organize-mode sidebar drag to reorder siblings
 *
 * Binds an "Organize" mode toggle to the sidebar toolbar button. When active,
 * folder <li> items become draggable within their sibling group. Dropping
 * commits the new order via AJAX (roci_reorder_folders). On AJAX failure the
 * DOM rolls back to the pre-drag state via document fragment re-sort.
 *
 * Organise mode guard: folders-dragdrop.js binds a capture-phase dragstart
 * listener on the sidebar that calls preventDefault() when organize mode is
 * off, so this bubble-phase handler only fires when the mode is active.
 *
 * File:    dist/js/folders/folders-reorder.js
 * Version: 1.1.0
 * Updated: 2026-05-16
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// ── Guard: only run on upload.php ──────────────────────────────────────
	if ( window.location.pathname.indexOf( 'upload.php' ) === -1 ) {
		return;
	}

	// ── Guard: require localised data ──────────────────────────────────────
	if ( typeof rociFoldersReorder === 'undefined' ) {
		return;
	}


	// ======================================================================
	// STATE
	// ======================================================================

	var sidebar              = null; // resolved by poll-until-ready
	var draggedLi            = null;
	var draggedTermId        = null;
	var draggedParentId      = null;
	var originalContainer    = null; // parent <ul> at dragstart — captured for rollback
	var originalSiblingOrder = [];   // real term ID strings in original order
	var indicator            = null; // single reusable insertion-line <div>


	// ======================================================================
	// ORGANIZE MODE
	// ======================================================================

	function setOrganizeMode( active ) {
		if ( active ) {
			sidebar.classList.add( 'roci-organize-mode' );
		} else {
			sidebar.classList.remove( 'roci-organize-mode' );
		}
		var btn = sidebar.querySelector( '.roci-action-organize' );
		if ( btn ) {
			btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		}
	}

	function handleOrganizeClick() {
		var btn      = sidebar.querySelector( '.roci-action-organize' );
		var isActive = btn && btn.getAttribute( 'aria-pressed' ) === 'true';
		setOrganizeMode( ! isActive );
	}


	// ======================================================================
	// INDICATOR
	// ======================================================================

	function ensureIndicator() {
		if ( ! indicator ) {
			indicator = document.createElement( 'div' );
			indicator.className = 'roci-drop-indicator';
		}
		return indicator;
	}


	// ======================================================================
	// DRAG START
	// ======================================================================

	function handleDragStart( e ) {
		var li = e.target.closest( 'li.roci-folder-item' );
		if ( ! li ) {
			return;
		}

		var termId = li.dataset.term;
		if ( ! termId || termId === '__all__' || termId === '__unassigned__' ) {
			return;
		}

		// Safety net — capture-phase listener in folders-dragdrop.js should
		// have already blocked this when organize mode is off.
		if ( ! sidebar.classList.contains( 'roci-organize-mode' ) ) {
			return;
		}

		draggedLi         = li;
		draggedTermId     = termId;
		draggedParentId   = li.dataset.parent || '0';
		originalContainer = li.parentElement;

		originalSiblingOrder = [];
		if ( originalContainer ) {
			var siblings = originalContainer.querySelectorAll( ':scope > li.roci-folder-item' );
			for ( var i = 0; i < siblings.length; i++ ) {
				var t = siblings[ i ].dataset.term;
				if ( t && t !== '__all__' && t !== '__unassigned__' ) {
					originalSiblingOrder.push( t );
				}
			}
		}

		e.dataTransfer.setData( 'text/plain', JSON.stringify( {
			termId:   draggedTermId,
			parentId: draggedParentId
		} ) );
		e.dataTransfer.effectAllowed = 'move';

		draggedLi.classList.add( 'is-dragging' );
		document.body.classList.add( 'roci-dragging-folder' );
	}


	// ======================================================================
	// DRAG OVER
	// ======================================================================

	function handleDragOver( e ) {
		var hoveredLi = e.target.closest( 'li.roci-folder-item' );
		if ( ! hoveredLi || hoveredLi === draggedLi ) {
			if ( indicator && indicator.parentNode ) {
				indicator.parentNode.removeChild( indicator );
			}
			return;
		}

		var termId = hoveredLi.dataset.term;
		if ( ! termId || termId === '__all__' || termId === '__unassigned__' ) {
			if ( indicator && indicator.parentNode ) {
				indicator.parentNode.removeChild( indicator );
			}
			return;
		}

		// Different sibling group — show no-drop cursor, do NOT call preventDefault.
		if ( hoveredLi.dataset.parent !== draggedParentId ) {
			if ( indicator && indicator.parentNode ) {
				indicator.parentNode.removeChild( indicator );
			}
			return;
		}

		e.preventDefault();

		var row = hoveredLi.querySelector( '.roci-item-row' );
		if ( ! row ) {
			return;
		}

		var rect      = row.getBoundingClientRect();
		var midY      = rect.top + rect.height / 2;
		var ind       = ensureIndicator();
		var container = hoveredLi.parentElement;

		if ( e.clientY < midY ) {
			container.insertBefore( ind, hoveredLi );
		} else {
			container.insertBefore( ind, hoveredLi.nextElementSibling );
		}
	}


	// ======================================================================
	// DRAG LEAVE
	// ======================================================================

	function handleDragLeave( e ) {
		if ( ! indicator || ! indicator.parentElement ) {
			return;
		}
		var container = indicator.parentElement;
		if ( e.relatedTarget && container.contains( e.relatedTarget ) ) {
			return;
		}
		container.removeChild( indicator );
	}


	// ======================================================================
	// DROP
	// ======================================================================

	function handleDrop( e ) {
		e.preventDefault();

		if ( ! indicator || ! indicator.parentElement ) {
			return;
		}

		var container = indicator.parentElement;
		container.insertBefore( draggedLi, indicator );
		container.removeChild( indicator );

		var newOrderedIds = [];
		var items = container.querySelectorAll( ':scope > li.roci-folder-item' );
		for ( var i = 0; i < items.length; i++ ) {
			var t = items[ i ].dataset.term;
			if ( t && t !== '__all__' && t !== '__unassigned__' ) {
				newOrderedIds.push( t );
			}
		}

		// No-op: user dropped the item in its original position.
		if ( newOrderedIds.join( ',' ) === originalSiblingOrder.join( ',' ) ) {
			return;
		}

		performReorder( draggedParentId, newOrderedIds );
	}


	// ======================================================================
	// DRAG END
	// ======================================================================

	function handleDragEnd() {
		if ( draggedLi ) {
			draggedLi.classList.remove( 'is-dragging' );
		}
		document.body.classList.remove( 'roci-dragging-folder' );
		if ( indicator && indicator.parentElement ) {
			indicator.parentElement.removeChild( indicator );
		}

		draggedLi            = null;
		draggedTermId        = null;
		draggedParentId      = null;
		originalContainer    = null;
		originalSiblingOrder = [];
	}


	// ======================================================================
	// AJAX REORDER
	// ======================================================================

	function performReorder( parentId, orderedIds ) {
		// Capture snapshots before handleDragEnd (fires synchronously after
		// handleDrop) clears the shared state variables.
		var snapshotCont  = originalContainer;
		var snapshot      = originalSiblingOrder.slice();

		var fd = new FormData();
		fd.append( 'action',           'roci_reorder_folders' );
		fd.append( 'nonce',            rociFoldersReorder.nonce );
		fd.append( 'parent_id',        String( parentId ) );
		fd.append( 'ordered_term_ids', orderedIds.join( ',' ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociFoldersReorder.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status >= 200 && xhr.status < 300 ) {
				var resp;
				try { resp = JSON.parse( xhr.responseText ); } catch ( err ) { resp = null; }
				if ( resp && resp.success ) {
					if ( resp.data && resp.data.options ) {
						document.dispatchEvent( new CustomEvent( 'roci:folderOrderChanged', {
							detail: { options: resp.data.options }
						} ) );
					}
					return;
				}
				var detail = ( resp && resp.data ) ? resp.data : 'Unknown error';
				rollbackDOM( snapshotCont, snapshot );
				console.error( '[roci-reorder] Reorder failed:', detail );
			} else {
				rollbackDOM( snapshotCont, snapshot );
				console.error( '[roci-reorder] Reorder failed: HTTP', xhr.status );
			}
		};

		xhr.onerror = function () {
			rollbackDOM( snapshotCont, snapshot );
			console.error( '[roci-reorder] Reorder failed: network error' );
		};

		xhr.send( fd );
	}


	// ======================================================================
	// DOM ROLLBACK
	// ======================================================================

	function rollbackDOM( container, originalOrder ) {
		if ( ! container ) {
			return;
		}

		var items   = container.querySelectorAll( ':scope > li.roci-folder-item' );
		var itemMap = {};
		for ( var i = 0; i < items.length; i++ ) {
			var t = items[ i ].dataset.term;
			if ( t && t !== '__all__' && t !== '__unassigned__' ) {
				itemMap[ t ] = items[ i ];
			}
		}

		var frag = document.createDocumentFragment();
		for ( var j = 0; j < originalOrder.length; j++ ) {
			if ( itemMap[ originalOrder[ j ] ] ) {
				frag.appendChild( itemMap[ originalOrder[ j ] ] );
			}
		}
		container.appendChild( frag );
	}


	// ======================================================================
	// INIT
	// ======================================================================

	function init() {
		sidebar = document.getElementById( 'roci-folders-sidebar' );
		if ( ! sidebar ) {
			return false;
		}

		var organizeBtn = sidebar.querySelector( '.roci-action-organize' );
		if ( organizeBtn ) {
			organizeBtn.addEventListener( 'click', handleOrganizeClick );
		}

		// Bubble phase — folders-dragdrop.js capture-phase listeners run first,
		// then these handlers process organize-mode drags.
		sidebar.addEventListener( 'dragstart', handleDragStart );
		sidebar.addEventListener( 'dragover',  handleDragOver );
		sidebar.addEventListener( 'dragleave', handleDragLeave );
		sidebar.addEventListener( 'drop',      handleDrop );
		sidebar.addEventListener( 'dragend',   handleDragEnd );

		return true;
	}

	// Sidebar is injected via admin_footer so it may not exist at DOMContentLoaded.
	// Poll until it appears (same pattern as folders-dragdrop.js).
	( function () {
		if ( document.readyState !== 'loading' ) {
			if ( init() ) {
				return;
			}
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( init() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();

} )();
