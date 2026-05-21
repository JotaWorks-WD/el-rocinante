/* global rociPostDragDrop */

/**
 * Folders Post Drag-and-Drop — post-to-folder assignment
 *
 * Listens for dragstart on .roci-post-drag-handle elements in the posts
 * list table and registers the sidebar folder rows as drop targets.
 * On drop, fires an AJAX call to roci_move_item_to_folder and shows a
 * toast notification with an Undo button.
 *
 * Drag type: 'text/x-roci-post' — distinct from the page drag type
 * ('text/x-roci-page') and the folder reorder type ('text/plain' JSON)
 * so dragover handlers can safely differentiate post drags.
 *
 * Works alongside folders-reorder.js (organize-mode folder reorder)
 * without conflict: reorder uses text/plain and gates on organize-mode;
 * this script uses text/x-roci-post and ignores non-post drags.
 *
 * File:    dist/js/folders/folders-post-dragdrop.js
 * Version: 1.0.0
 * Updated: 2026-05-21
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// ── Guard: only run on edit.php for the post post type ────────────────
	if ( window.location.href.indexOf( 'edit.php' ) === -1 ) {
		return;
	}
	var urlParams   = new URLSearchParams( window.location.search );
	var urlPostType = urlParams.get( 'post_type' ) || 'post';
	if ( urlPostType !== 'post' ) {
		return;
	}

	// ── Guard: require localised data ──────────────────────────────────────
	if ( typeof rociPostDragDrop === 'undefined' ) {
		return;
	}


	// ======================================================================
	// STATE
	// ======================================================================

	var draggedPostId    = '';
	var draggedPostTitle = '';


	// ======================================================================
	// DRAG SOURCE — .roci-post-drag-handle
	// ======================================================================

	document.addEventListener( 'dragstart', function ( e ) {
		var handle = e.target.closest( '.roci-post-drag-handle' );
		if ( ! handle ) {
			return;
		}

		draggedPostId = handle.dataset.postId || '';
		if ( ! draggedPostId ) {
			return;
		}

		// Read the post title from the list table row for use in the toast.
		var row = handle.closest( 'tr' );
		draggedPostTitle = '';
		if ( row ) {
			var titleEl = row.querySelector( '.column-title a.row-title' );
			if ( titleEl ) {
				draggedPostTitle = titleEl.textContent.trim();
			}
		}

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/x-roci-post', draggedPostId );

		document.body.classList.add( 'roci-dragging-post' );
	}, false );

	document.addEventListener( 'dragend', function ( e ) {
		if ( ! e.target.closest( '.roci-post-drag-handle' ) ) {
			return;
		}
		document.body.classList.remove( 'roci-dragging-post' );
		// Defensive: clear any orphaned drop-target highlights.
		document.querySelectorAll( '.roci-folder-item.is-drop-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-drop-target' );
		} );
	}, false );


	// ======================================================================
	// HELPERS
	// ======================================================================

	// Check whether the current drag event carries a post payload.
	function isPostDrag( e ) {
		var types = e.dataTransfer.types;
		for ( var i = 0; i < types.length; i++ ) {
			if ( types[ i ] === 'text/x-roci-post' ) {
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
	function getFilterContext() {
		var params = new URLSearchParams( window.location.search );
		return {
			folderTermId: parseInt( params.get( 'roci_post_folder' ), 10 ) || 0,
			isUnassigned: params.get( 'roci_no_folder' ) === '1'
		};
	}

	// Adjust the "N item(s)" labels in both tablenav bars by delta (+1 or -1).
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
	function applyTableUpdate( postId, newFolderTermId, newFolderName ) {
		var ctx         = getFilterContext();
		var moveContext = {
			case:                 'noChange',
			row:                  null,
			nextSibling:          null,
			parent:               null,
			folderCell:           null,
			folderCellOriginalHTML: null
		};
		var row, cell;

		if ( ctx.folderTermId ) {
			// Case A — specific folder view: remove if post left this folder.
			if ( newFolderTermId !== ctx.folderTermId ) {
				row = document.getElementById( 'post-' + postId );
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
			// Case B — Unassigned Posts view: remove if post is now assigned.
			if ( newFolderTermId ) {
				row = document.getElementById( 'post-' + postId );
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
			// Case C — All Posts view: update the taxonomy cell in place.
			row = document.getElementById( 'post-' + postId );
			if ( row ) {
				cell = row.querySelector( '.column-taxonomy-roci_post_folder' );
				if ( cell ) {
					moveContext.case                  = 'cellUpdated';
					moveContext.folderCell            = cell;
					moveContext.folderCellOriginalHTML = cell.innerHTML;
					cell.textContent = newFolderTermId ? ( newFolderName || '' ) : '—';
				}
			}
		}

		return moveContext;
	}

	// Reverse the DOM change recorded in moveContext (called on successful Undo).
	function restoreTableUpdate( moveContext ) {
		if ( ! moveContext || moveContext.case === 'noChange' ) {
			return;
		}

		if ( moveContext.case === 'removed' ) {
			if ( ! moveContext.parent || ! document.contains( moveContext.parent ) ) {
				return;
			}
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
			if ( ! isPostDrag( e ) ) {
				return;
			}
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			// __all__ (All Posts) is view-only — not a valid drop target.
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
			if ( ! isPostDrag( e ) ) {
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

			var postId = e.dataTransfer.getData( 'text/x-roci-post' );
			if ( ! postId ) {
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

			performMove( postId, targetTerm, draggedPostTitle, folderName, false );
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

	function performMove( postId, targetTerm, postTitle, folderName, isUndo, undoMoveContext ) {

		var fd = new FormData();
		fd.append( 'action',      'roci_move_item_to_folder' );
		fd.append( 'nonce',       rociPostDragDrop.nonce );
		fd.append( 'post_id',     String( postId ) );
		fd.append( 'target_term', String( targetTerm ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociPostDragDrop.ajaxUrl );

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
					var resolvedTitle  = ( resp.data && resp.data.post_title )     ? resp.data.post_title     : postTitle;
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
						restoreTableUpdate( undoMoveContext );
						rociShowToast( { message: rociPostDragDrop.i18n.undone, duration: 3000 } );
						return;
					}

					var moveContext = applyTableUpdate( postId, newFolderTermId, newFolderName );

					var msg;
					if ( targetTerm === '__unassigned__' ) {
						msg = rociPostDragDrop.i18n.movedUnassigned.replace( '%s', resolvedTitle );
					} else {
						msg = rociPostDragDrop.i18n.moved
							.replace( '%s', resolvedTitle )
							.replace( '%s', resolvedFolder );
					}

					rociShowToast( {
						message:      msg,
						undoCallback: function () {
							var undoTerm = ( previousTerms.length > 0 )
								? String( previousTerms[ 0 ] )
								: '__unassigned__';
							performMove( postId, undoTerm, resolvedTitle, '', true, moveContext );
						}
					} );

					return;
				}

				// Server returned success:false.
				var detail = ( resp && resp.data ) ? resp.data : rociPostDragDrop.i18n.error;
				console.error( '[roci-post-dragdrop] Move failed:', detail );

			} else {
				console.error( '[roci-post-dragdrop] Move failed: HTTP', xhr.status );
			}
		};

		xhr.onerror = function () {
			console.error( '[roci-post-dragdrop] Move failed: network error' );
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
			undoBtn.textContent = rociPostDragDrop.i18n.undo;
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
