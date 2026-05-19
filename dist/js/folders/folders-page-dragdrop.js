/* global rociPageDragDrop */

/**
 * Folders Page Drag-and-Drop — page-to-folder assignment
 *
 * Listens for dragstart on .roci-page-drag-handle elements in the pages
 * list table and registers the sidebar folder rows as drop targets.
 * On drop, fires an AJAX call to roci_move_page_to_folder and shows a
 * toast notification with an Undo button.
 *
 * Drag type: 'text/x-roci-page' — distinct from the media drag type
 * ('text/plain' JSON) and the folder reorder type ('text/plain' JSON)
 * so dragover handlers can safely differentiate page drags without
 * processing unrelated drags on the same sidebar.
 *
 * Works alongside folders-reorder.js (organize-mode folder reorder)
 * without conflict: reorder uses text/plain and gates on organize-mode;
 * this script uses text/x-roci-page and ignores non-page drags.
 *
 * File:    dist/js/folders/folders-page-dragdrop.js
 * Version: 1.0.0
 * Updated: 2026-05-19
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// ── Guard: only run on edit.php?post_type=page ─────────────────────────
	if ( window.location.href.indexOf( 'edit.php' ) === -1 ||
	     window.location.href.indexOf( 'post_type=page' ) === -1 ) {
		return;
	}

	// ── Guard: require localised data ──────────────────────────────────────
	if ( typeof rociPageDragDrop === 'undefined' ) {
		return;
	}


	// ======================================================================
	// STATE
	// ======================================================================

	var draggedPageId    = '';
	var draggedPageTitle = '';


	// ======================================================================
	// DRAG SOURCE — .roci-page-drag-handle
	// ======================================================================

	document.addEventListener( 'dragstart', function ( e ) {
		var handle = e.target.closest( '.roci-page-drag-handle' );
		if ( ! handle ) {
			return;
		}

		draggedPageId = handle.dataset.pageId || '';
		if ( ! draggedPageId ) {
			return;
		}

		// Read the page title from the list table row for use in the toast.
		var row = handle.closest( 'tr' );
		draggedPageTitle = '';
		if ( row ) {
			var titleEl = row.querySelector( '.column-title a.row-title' );
			if ( titleEl ) {
				draggedPageTitle = titleEl.textContent.trim();
			}
		}

		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/x-roci-page', draggedPageId );

		document.body.classList.add( 'roci-dragging-page' );
	}, false );

	document.addEventListener( 'dragend', function ( e ) {
		if ( ! e.target.closest( '.roci-page-drag-handle' ) ) {
			return;
		}
		document.body.classList.remove( 'roci-dragging-page' );
		// Defensive: clear any orphaned drop-target highlights.
		document.querySelectorAll( '.roci-folder-item.is-drop-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-drop-target' );
		} );
	}, false );


	// ======================================================================
	// HELPERS
	// ======================================================================

	// Check whether the current drag event carries a page payload.
	// types is readable on dragenter/dragover/dragleave — not getData.
	function isPageDrag( e ) {
		var types = e.dataTransfer.types;
		for ( var i = 0; i < types.length; i++ ) {
			if ( types[ i ] === 'text/x-roci-page' ) {
				return true;
			}
		}
		return false;
	}

	// Extract just the folder name from a .roci-folder-link node,
	// stripping the (count) span that trails the text.
	function folderNameFromLink( linkEl ) {
		var clone    = linkEl.cloneNode( true );
		var countEl  = clone.querySelector( '.roci-folder-count' );
		if ( countEl ) {
			countEl.parentNode.removeChild( countEl );
		}
		return clone.textContent.trim();
	}


	// ======================================================================
	// DROP TARGETS — delegated listeners on the sidebar
	// ======================================================================

	function bindSidebarDropTargets( sidebar ) {

		function onDragOver( e ) {
			if ( ! isPageDrag( e ) ) {
				return;
			}
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			// __all__ (All Pages) is view-only — not a valid drop target.
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
			if ( ! isPageDrag( e ) ) {
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

			var pageId = e.dataTransfer.getData( 'text/x-roci-page' );
			if ( ! pageId ) {
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

			performMove( pageId, targetTerm, draggedPageTitle, folderName, false );
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

	function performMove( pageId, targetTerm, pageTitle, folderName, isUndo ) {

		var fd = new FormData();
		fd.append( 'action',      'roci_move_page_to_folder' );
		fd.append( 'nonce',       rociPageDragDrop.nonce );
		fd.append( 'page_id',     String( pageId ) );
		fd.append( 'target_term', String( targetTerm ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociPageDragDrop.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status >= 200 && xhr.status < 300 ) {

				var resp;
				try { resp = JSON.parse( xhr.responseText ); } catch ( err ) { resp = null; }

				if ( resp && resp.success ) {

					// Server-detected no-op — already in the target folder.
					if ( resp.data && resp.data.no_change ) {
						return;
					}

					var previousTerms   = ( resp.data && resp.data.previous_terms ) ? resp.data.previous_terms : [];
					var resolvedTitle   = ( resp.data && resp.data.page_title )     ? resp.data.page_title     : pageTitle;
					var resolvedFolder  = ( resp.data && resp.data.target_folder_name && resp.data.target_folder_name !== '' )
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

					if ( isUndo ) {
						rociShowToast( { message: rociPageDragDrop.i18n.undone, duration: 3000 } );
						return;
					}

					var msg;
					if ( targetTerm === '__unassigned__' ) {
						msg = rociPageDragDrop.i18n.movedUnassigned.replace( '%s', resolvedTitle );
					} else {
						msg = rociPageDragDrop.i18n.moved
							.replace( '%s', resolvedTitle )
							.replace( '%s', resolvedFolder );
					}

					rociShowToast( {
						message:      msg,
						undoCallback: function () {
							var undoTerm = ( previousTerms.length > 0 )
								? String( previousTerms[ 0 ] )
								: '__unassigned__';
							performMove( pageId, undoTerm, resolvedTitle, '', true );
						}
					} );

					return;
				}

				// Server returned success:false — log the error detail.
				var detail = ( resp && resp.data ) ? resp.data : rociPageDragDrop.i18n.error;
				console.error( '[roci-page-dragdrop] Move failed:', detail );

			} else {
				console.error( '[roci-page-dragdrop] Move failed: HTTP', xhr.status );
			}
		};

		xhr.onerror = function () {
			console.error( '[roci-page-dragdrop] Move failed: network error' );
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
			undoBtn.type      = 'button';
			undoBtn.className = 'roci-toast__undo';
			undoBtn.textContent = rociPageDragDrop.i18n.undo;
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
