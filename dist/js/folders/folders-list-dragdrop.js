/* global rociDragDrop */

/**
 * Folders List-Table Drag-and-Drop — unified config-driven post-to-folder assignment
 *
 * A single file serves all registered folder-enabled post types (Pages,
 * Posts, any child-theme CPTs). Per-post-type behaviour is driven entirely
 * by the rociDragDrop config object localised from PHP; no post-type strings
 * are hardcoded here.
 *
 * Config shape (provided via wp_localize_script / rociDragDrop):
 *   postType      — WP post_type slug, e.g. 'page', 'post', 'tour'
 *   handleClass   — CSS class on the drag handle cell, e.g. 'roci-page-drag-handle'
 *   datasetAttr   — dataset key for the post ID, e.g. 'pageId', 'postId'
 *   dragType      — MIME type for the drag payload, e.g. 'text/x-roci-page'
 *   filterKey     — URL param for the folder filter, e.g. 'roci_page_folder'
 *   columnClass   — CSS class for the taxonomy column cell
 *   bodyDragClass — CSS class added to <body> during a drag
 *   ajaxUrl, nonce, i18n — same as the former per-post-type files
 *
 * Drag type is kept distinct per post type ('text/x-roci-page',
 * 'text/x-roci-post', etc.) so drop handlers can discriminate during
 * dragover/dragenter without reading the payload (blocked until drop).
 *
 * Note: dist/js/folders/folders-dragdrop.js handles the Media Library grid
 * view (upload.php, Backbone). This file is for edit.php list tables only.
 *
 * Works alongside folders-reorder.js without conflict: reorder uses
 * text/plain and gates on organize-mode; this script uses its own MIME
 * type and ignores other drags.
 *
 * File:    dist/js/folders/folders-list-dragdrop.js
 * Version: 1.0.0
 * Updated: 2026-05-21
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// ── Guard: require localised config ───────────────────────────────────
	if ( typeof rociDragDrop === 'undefined' ) {
		return;
	}
	var config = rociDragDrop;

	// ── Guard: only run on the correct edit.php screen ────────────────────
	// For 'post' (the WP default post type), accept both explicit
	// ?post_type=post and the absence of the parameter.
	if ( window.location.href.indexOf( 'edit.php' ) === -1 ) {
		return;
	}
	var urlParams   = new URLSearchParams( window.location.search );
	var urlPostType = urlParams.get( 'post_type' ) || 'post';
	if ( urlPostType !== config.postType ) {
		return;
	}


	// ======================================================================
	// STATE
	// ======================================================================

	var draggedItemId    = '';
	var draggedItemTitle = '';


	// ======================================================================
	// DRAG SOURCE
	// ======================================================================

	document.addEventListener( 'dragstart', function ( e ) {
		var handle = e.target.closest( '.' + config.handleClass );
		if ( ! handle ) {
			return;
		}

		draggedItemId = handle.dataset[ config.datasetAttr ] || '';
		if ( ! draggedItemId ) {
			return;
		}

		var row = handle.closest( 'tr' );
		draggedItemTitle = '';
		if ( row ) {
			var titleEl = row.querySelector( '.column-title a.row-title' );
			if ( titleEl ) {
				draggedItemTitle = titleEl.textContent.trim();
			}
		}

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( config.dragType, draggedItemId );

		document.body.classList.add( config.bodyDragClass );
	}, false );

	document.addEventListener( 'dragend', function ( e ) {
		if ( ! e.target.closest( '.' + config.handleClass ) ) {
			return;
		}
		document.body.classList.remove( config.bodyDragClass );
		// Defensive: clear any orphaned drop-target highlights.
		document.querySelectorAll( '.roci-folder-item.is-drop-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-drop-target' );
		} );
	}, false );


	// ======================================================================
	// HELPERS
	// ======================================================================

	// Check whether the current drag event carries this post type's payload.
	// types is readable on dragenter/dragover/dragleave — not getData.
	function isCorrectDrag( e ) {
		var types = e.dataTransfer.types;
		for ( var i = 0; i < types.length; i++ ) {
			if ( types[ i ] === config.dragType ) {
				return true;
			}
		}
		return false;
	}

	// Extract just the folder name from a .roci-folder-link node,
	// stripping the (count) span that trails the text.
	function folderNameFromLink( linkEl ) {
		var clone   = linkEl.cloneNode( true );
		var countEl = clone.querySelector( '.roci-folder-count' );
		if ( countEl ) {
			countEl.parentNode.removeChild( countEl );
		}
		return clone.textContent.trim();
	}


	// ======================================================================
	// TABLE UPDATE — live DOM sync after a successful move
	// ======================================================================

	// Return the current folder filter context from the URL.
	// Unassigned uses ?roci_no_folder=1 (not a taxonomy param).
	function getFilterContext() {
		var params = new URLSearchParams( window.location.search );
		return {
			folderTermId: parseInt( params.get( config.filterKey ), 10 ) || 0,
			isUnassigned: params.get( 'roci_no_folder' ) === '1'
		};
	}

	// Adjust the "N item(s)" labels in both tablenav bars by delta (+1 or -1).
	// Only the digit is replaced; the translated word (item/items) is left
	// as-is to avoid duplicating WP's pluralisation logic.
	function adjustItemCount( delta ) {
		document.querySelectorAll( '.tablenav .displaying-num' ).forEach( function ( el ) {
			var prev = parseInt( el.textContent.replace( /[^0-9]/g, '' ), 10 );
			if ( isNaN( prev ) ) {
				return;
			}
			var next = Math.max( 0, prev + delta );
			el.textContent = el.textContent.replace( /\d[\d,]*/, String( next ) );
		} );
	}

	// Apply the correct table update based on the current filter view.
	// Stashes the pre-mutation DOM state and returns it so Undo can reverse
	// the change via restoreTableUpdate().
	//
	//   Case A (folder filter) : remove row if item left this folder.
	//   Case B (unassigned)    : remove row if item is now assigned.
	//   Case C (all items)     : update the folder cell in place (no removal).
	function applyTableUpdate( itemId, newFolderTermId, newFolderName ) {
		var ctx         = getFilterContext();
		var moveContext = {
			case:                  'noChange',
			row:                   null,
			nextSibling:           null,
			parent:                null,
			folderCell:            null,
			folderCellOriginalHTML: null
		};
		var row, cell;

		if ( ctx.folderTermId ) {
			// Case A — specific folder view: remove if item left this folder.
			if ( newFolderTermId !== ctx.folderTermId ) {
				row = document.getElementById( 'post-' + itemId );
				if ( row && row.parentNode ) {
					moveContext.case        = 'removed';
					moveContext.row         = row;
					moveContext.nextSibling = row.nextElementSibling;
					moveContext.parent      = row.parentNode;
					row.parentNode.removeChild( row );
					adjustItemCount( -1 );
				}
			}

		} else if ( ctx.isUnassigned ) {
			// Case B — Unassigned view: remove if item is now assigned.
			if ( newFolderTermId ) {
				row = document.getElementById( 'post-' + itemId );
				if ( row && row.parentNode ) {
					moveContext.case        = 'removed';
					moveContext.row         = row;
					moveContext.nextSibling = row.nextElementSibling;
					moveContext.parent      = row.parentNode;
					row.parentNode.removeChild( row );
					adjustItemCount( -1 );
				}
			}

		} else {
			// Case C — All items view: update the taxonomy cell in place.
			row = document.getElementById( 'post-' + itemId );
			if ( row ) {
				cell = row.querySelector( '.' + config.columnClass );
				if ( cell ) {
					moveContext.case                  = 'cellUpdated';
					moveContext.folderCell            = cell;
					moveContext.folderCellOriginalHTML = cell.innerHTML;
					cell.textContent = newFolderTermId ? ( newFolderName || '' ) : '—'; // — for unassigned
				}
			}
		}

		return moveContext;
	}

	// Reverse the DOM change recorded in moveContext (called on successful Undo).
	//
	// Navigation guard: if the user clicked a sidebar folder between the move
	// and the Undo click, the parent tbody is no longer in the document. In that
	// case skip DOM restoration — the AJAX already corrected server state, and
	// the freshly loaded view will render the correct list automatically.
	function restoreTableUpdate( moveContext ) {
		if ( ! moveContext || moveContext.case === 'noChange' ) {
			return;
		}

		if ( moveContext.case === 'removed' ) {
			if ( ! moveContext.parent || ! document.contains( moveContext.parent ) ) {
				return; // stale reference — parent left the document after navigation
			}
			// insertBefore(row, null) appends to end, which is correct when the
			// row was originally the last sibling and nextSibling is null.
			moveContext.parent.insertBefore( moveContext.row, moveContext.nextSibling );
			adjustItemCount( +1 );

		} else if ( moveContext.case === 'cellUpdated' ) {
			if ( moveContext.folderCell ) {
				moveContext.folderCell.innerHTML = moveContext.folderCellOriginalHTML;
			}
		}
	}


	// ======================================================================
	// DROP TARGETS — delegated listeners on the sidebar
	// ======================================================================

	function bindSidebarDropTargets( sidebar ) {

		function onDragOver( e ) {
			if ( ! isCorrectDrag( e ) ) {
				return;
			}
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			// __all__ (All Pages/Posts/etc.) is view-only — not a valid drop target.
			if ( target.dataset.term === '__all__' ) {
				return;
			}
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			target.classList.add( 'is-drop-target' );
		}

		sidebar.addEventListener( 'dragenter', onDragOver );
		sidebar.addEventListener( 'dragover',  onDragOver );

		sidebar.addEventListener( 'dragleave', function ( e ) {
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			if ( e.relatedTarget && target.contains( e.relatedTarget ) ) {
				return;
			}
			target.classList.remove( 'is-drop-target' );
		} );

		sidebar.addEventListener( 'drop', function ( e ) {
			if ( ! isCorrectDrag( e ) ) {
				return;
			}
			e.preventDefault();

			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			target.classList.remove( 'is-drop-target' );

			var targetTerm = target.dataset.term;
			if ( ! targetTerm || targetTerm === '__all__' ) {
				return;
			}

			var itemId = e.dataTransfer.getData( config.dragType );
			if ( ! itemId ) {
				return;
			}

			// Resolve folder display name for the toast message.
			var folderName = '';
			if ( targetTerm !== '__unassigned__' ) {
				var link = target.querySelector( '.roci-folder-link' );
				if ( link ) {
					folderName = folderNameFromLink( link );
				}
			}

			performMove( itemId, targetTerm, draggedItemTitle, folderName, false );
		} );
	}

	function initDropTargets() {
		var sidebar = document.getElementById( 'roci-folders-sidebar' );
		if ( ! sidebar ) {
			return false;
		}
		bindSidebarDropTargets( sidebar );
		return true;
	}

	// Sidebar is injected via admin_footer — poll until it appears.
	( function () {
		if ( document.readyState !== 'loading' ) {
			if ( initDropTargets() ) {
				return;
			}
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( initDropTargets() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();


	// ======================================================================
	// AJAX MOVE
	// ======================================================================

	function performMove( itemId, targetTerm, itemTitle, folderName, isUndo, undoMoveContext ) {

		var fd = new FormData();
		fd.append( 'action',      'roci_move_item_to_folder' );
		fd.append( 'nonce',       config.nonce );
		fd.append( 'post_id',     String( itemId ) );
		fd.append( 'target_term', String( targetTerm ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', config.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status >= 200 && xhr.status < 300 ) {

				var resp;
				try { resp = JSON.parse( xhr.responseText ); } catch ( err ) { resp = null; }

				if ( resp && resp.success ) {

					// Server-detected no-op — already in the target folder.
					if ( resp.data && resp.data.no_change ) {
						return;
					}

					var previousTerms  = ( resp.data && resp.data.previous_terms ) ? resp.data.previous_terms : [];
					var resolvedTitle  = ( resp.data && resp.data.post_title )     ? resp.data.post_title     : itemTitle;
					var resolvedFolder = ( resp.data && resp.data.target_folder_name && resp.data.target_folder_name !== '' )
					                       ? resp.data.target_folder_name
					                       : folderName;

					// Update sidebar count badges.
					if ( typeof window.rociDecrementSidebarCount === 'function' ) {
						if ( previousTerms.length > 0 ) {
							previousTerms.forEach( function ( tid ) {
								window.rociDecrementSidebarCount( tid );
							} );
						} else {
							window.rociDecrementSidebarCount( '__unassigned__' );
						}
					}
					if ( typeof window.rociIncrementSidebarCount === 'function' ) {
						if ( targetTerm === '__unassigned__' ) {
							window.rociIncrementSidebarCount( '__unassigned__' );
						} else {
							window.rociIncrementSidebarCount( parseInt( targetTerm, 10 ) );
						}
					}

					var newFolderTermId = resp.data.new_folder_term_id || 0;
					var newFolderName   = resp.data.new_folder_name    || '';

					if ( isUndo ) {
						// Restore the DOM change recorded during the forward move.
						// restoreTableUpdate's document.contains() guard handles
						// the case where the user navigated between move and Undo.
						restoreTableUpdate( undoMoveContext );
						rociShowToast( { message: config.i18n.undone, duration: 3000 } );
						return;
					}

					// Forward move — apply DOM change and stash context for Undo.
					var moveContext = applyTableUpdate( itemId, newFolderTermId, newFolderName );

					var msg;
					if ( targetTerm === '__unassigned__' ) {
						msg = config.i18n.movedUnassigned.replace( '%s', resolvedTitle );
					} else {
						msg = config.i18n.moved
							.replace( '%s', resolvedTitle )
							.replace( '%s', resolvedFolder );
					}

					rociShowToast( {
						message:      msg,
						undoCallback: function () {
							var undoTerm = ( previousTerms.length > 0 )
								? String( previousTerms[ 0 ] )
								: '__unassigned__';
							performMove( itemId, undoTerm, resolvedTitle, '', true, moveContext );
						}
					} );

					return;
				}

				// Server returned success:false — log the error detail.
				var detail = ( resp && resp.data ) ? resp.data : config.i18n.error;
				console.error( '[roci-list-dragdrop] Move failed:', detail );

			} else {
				console.error( '[roci-list-dragdrop] Move failed: HTTP', xhr.status );
			}
		};

		xhr.onerror = function () {
			console.error( '[roci-list-dragdrop] Move failed: network error' );
		};

		xhr.send( fd );
	}


	// ======================================================================
	// TOAST NOTIFICATION
	// ======================================================================

	var currentToast   = null;
	var currentTimeout = null;

	function rociShowToast( opts ) {

		// Replace any in-flight toast immediately.
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
			undoBtn.textContent = config.i18n.undo;
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

		// Trigger entrance animation on next frame so the transition fires.
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				toast.classList.add( 'roci-toast--visible' );
			} );
		} );

		currentTimeout = setTimeout( dismiss, opts.duration || 8000 );
	}

} )();
