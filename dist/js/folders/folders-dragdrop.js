/* global wp, rociFoldersDragDrop, _ */

/**
 * Folders Drag-and-Drop — grid-view attachment-to-folder reassignment
 *
 * Extends wp.media.view.Attachment.Library to make grid thumbnails
 * draggable. Binds delegated dragenter/dragover/dragleave/drop listeners
 * on the sidebar so any folder item is a valid drop target.
 *
 * Drag source:
 *   Each thumbnail gets draggable="true" via the Backbone attributes()
 *   override. rociOnDragStart serialises { id, sourceTerms } into
 *   dataTransfer as text/plain. rociOnDragEnd cleans up CSS classes.
 *
 * Drop targets:
 *   All .roci-folder-item nodes except [data-term="__all__"] accept drops.
 *   On drop: optimistic count update → AJAX move → rociForceLibraryRefresh
 *   on success, rollback on failure.
 *
 * Cross-script helpers:
 *   window.rociIncrementSidebarCount and window.rociDecrementSidebarCount
 *   are exposed by folders-sidebar.js and called here to update count badges
 *   without duplicating the DOM-mutation logic.
 *
 * Scope:
 *   Grid view on upload.php only. List-view drag-drop, modal drag-drop,
 *   multi-select drag, and folder reordering are out of scope for Phase 6.
 *
 * File:    dist/js/folders/folders-dragdrop.js
 * Version: 1.6.0
 * Updated: 2026-05-20
 *
 * @package ElRocinante
 */

( function () {

	'use strict';

	// ── Guard: only run on upload.php in grid view ─────────────────────────
	if ( window.location.pathname.indexOf( 'upload.php' ) === -1 ) {
		return;
	}

	// ── Guard: require localised data ──────────────────────────────────────
	if ( typeof rociFoldersDragDrop === 'undefined' ) {
		return;
	}


	// ======================================================================
	// DRAG SOURCE — extend wp.media.view.Attachment.Library
	// ======================================================================

	function extendAttachmentView() {

		if ( ! window.wp || ! wp.media || ! wp.media.view || ! wp.media.view.Attachment || ! wp.media.view.Attachment.Library ) {
			return false;
		}
		if ( wp.media.view.Attachment.Library.prototype._rociDragPatched ) {
			return true;
		}

		var OrigAttachment = wp.media.view.Attachment.Library;

		wp.media.view.Attachment.Library = OrigAttachment.extend( {

			// Add draggable="true" to the root element of each thumbnail.
			attributes: function () {
				var attrs = OrigAttachment.prototype.attributes
					? OrigAttachment.prototype.attributes.apply( this, arguments )
					: {};
				attrs.draggable = 'true';
				return attrs;
			},

			// Merge our drag events with any existing ones on the parent.
			events: _.extend( {}, OrigAttachment.prototype.events || {}, {
				'dragstart': 'rociOnDragStart',
				'dragend':   'rociOnDragEnd'
			} ),

			rociOnDragStart: function ( e ) {
				// Block single-photo drags while bulk selection mode is active.
				if ( typeof window.rociIsBulkSelectMode === 'function' && window.rociIsBulkSelectMode() ) {
					e.preventDefault();
					return;
				}
				var sourceTerms = this.model.get( 'roci_media_folder' ) || [];
				var payload     = JSON.stringify( {
					id:          this.model.id,
					sourceTerms: sourceTerms
				} );
				e.originalEvent.dataTransfer.setData( 'text/plain', payload );
				e.originalEvent.dataTransfer.effectAllowed = 'move';
				this.el.classList.add( 'is-dragging' );
				document.body.classList.add( 'roci-dragging' );
			},

			rociOnDragEnd: function () {
				this.el.classList.remove( 'is-dragging' );
				document.body.classList.remove( 'roci-dragging' );
				// Defensive: clear any orphaned drop-target highlights in case
				// drop did not fire (e.g. user released outside a valid target).
				document.querySelectorAll( '.roci-folder-item.is-drop-target' ).forEach( function ( el ) {
					el.classList.remove( 'is-drop-target' );
				} );
			}
		} );

		wp.media.view.Attachment.Library.prototype._rociDragPatched = true;
		return true;
	}

	// Attempt immediately; if wp.media isn't ready yet, poll.
	( function () {
		if ( extendAttachmentView() ) {
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( extendAttachmentView() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();


	// ======================================================================
	// OPTIMISTIC COUNTS
	// ======================================================================

	function applyOptimisticCounts( sourceTerms, targetTerm ) {
		// Decrement source locations.
		if ( Array.isArray( sourceTerms ) && sourceTerms.length > 0 ) {
			sourceTerms.forEach( function ( termId ) {
				if ( typeof window.rociDecrementSidebarCount === 'function' ) {
					window.rociDecrementSidebarCount( termId );
				}
			} );
		} else {
			// File was unassigned — decrement the unassigned bucket.
			if ( typeof window.rociDecrementSidebarCount === 'function' ) {
				window.rociDecrementSidebarCount( '__unassigned__' );
			}
		}

		// Increment destination.
		if ( targetTerm === '__unassigned__' ) {
			if ( typeof window.rociIncrementSidebarCount === 'function' ) {
				window.rociIncrementSidebarCount( '__unassigned__' );
			}
		} else {
			if ( typeof window.rociIncrementSidebarCount === 'function' ) {
				window.rociIncrementSidebarCount( parseInt( targetTerm, 10 ) );
			}
		}
		// __all__ count is unchanged by a move — no update needed.
	}

	function rollbackOptimisticCounts( sourceTerms, targetTerm ) {
		// Re-increment source locations.
		if ( Array.isArray( sourceTerms ) && sourceTerms.length > 0 ) {
			sourceTerms.forEach( function ( termId ) {
				if ( typeof window.rociIncrementSidebarCount === 'function' ) {
					window.rociIncrementSidebarCount( termId );
				}
			} );
		} else {
			if ( typeof window.rociIncrementSidebarCount === 'function' ) {
				window.rociIncrementSidebarCount( '__unassigned__' );
			}
		}

		// Re-decrement destination.
		if ( targetTerm === '__unassigned__' ) {
			if ( typeof window.rociDecrementSidebarCount === 'function' ) {
				window.rociDecrementSidebarCount( '__unassigned__' );
			}
		} else {
			if ( typeof window.rociDecrementSidebarCount === 'function' ) {
				window.rociDecrementSidebarCount( parseInt( targetTerm, 10 ) );
			}
		}
	}


	// ======================================================================
	// BACKBONE GRID UPDATE
	// ======================================================================

	// After a successful move, surgically remove the attachment from the
	// current Backbone library collection (filtered views only). This is
	// faster and more reliable than re-fetching because WP's custom
	// Attachments sync may call set() instead of reset() on the collection,
	// leaving the moved attachment visible in the grid.
	//
	// Also updates the attachment model's roci_media_folder attribute so
	// the next drag from any view uses the correct source terms.
	//
	// All-Files view: model attribute updated but no removal (file stays visible).
	// Filtered view (folder or unassigned): model removed → Backbone re-renders.

	function rociRemoveFromGrid( attachmentId, newTerms ) {
		if ( ! window.wp || ! wp.media || ! wp.media.frame ) {
			return;
		}
		try {
			var state   = wp.media.frame.state();
			var library = state && state.get( 'library' );
			if ( ! library ) {
				return;
			}

			// Always update the cached model's folder attribute so subsequent
			// drags (from any view) read the correct sourceTerms.
			var model = wp.media.attachment( attachmentId );
			if ( model && Array.isArray( newTerms ) ) {
				model.set( 'roci_media_folder', newTerms );
			}

			// Skip removal in All Files view — attachment remains visible.
			var props         = library.props;
			var currentFolder = props && props.get( 'roci_media_folder' );
			var isUnassigned  = props && !! props.get( 'roci_no_folder' );
			if ( ! currentFolder && ! isUnassigned ) {
				return;
			}

			// Filtered view — remove the model so Backbone re-renders immediately.
			if ( model ) {
				library.remove( model );
			}
		} catch ( e ) {}
	}


	// ======================================================================
	// AJAX MOVE
	// ======================================================================

	function performMove( attachmentId, sourceTerms, targetTerm ) {
		var fd = new FormData();
		fd.append( 'action',        'roci_move_attachment' );
		fd.append( 'nonce',         rociFoldersDragDrop.nonce );
		fd.append( 'attachment_id', String( attachmentId ) );
		fd.append( 'target_term',   String( targetTerm ) );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', rociFoldersDragDrop.ajaxUrl );

		xhr.onload = function () {
			if ( xhr.status >= 200 && xhr.status < 300 ) {
				var resp;
				try { resp = JSON.parse( xhr.responseText ); } catch ( e ) { resp = null; }
				if ( resp && resp.success ) {
					var newTerms = ( resp.data && Array.isArray( resp.data.new_terms ) )
						? resp.data.new_terms
						: [];
					rociRemoveFromGrid( attachmentId, newTerms );
					if ( typeof window.rociForceLibraryRefresh === 'function' ) {
						window.rociForceLibraryRefresh();
					}
					return;
				}
				// success:false — server rejected (shouldn't normally reach here with
				// the regularised error codes, but guard it anyway).
				rollbackOptimisticCounts( sourceTerms, targetTerm );
				var detail = ( resp && resp.data ) ? resp.data : 'Unknown error';
				console.error( '[roci-dragdrop] Move failed:', detail );
			} else {
				rollbackOptimisticCounts( sourceTerms, targetTerm );
				console.error( '[roci-dragdrop] Move failed: HTTP', xhr.status );
			}
		};

		xhr.onerror = function () {
			rollbackOptimisticCounts( sourceTerms, targetTerm );
			console.error( '[roci-dragdrop] Move failed: network error' );
		};

		xhr.send( fd );
	}


	// ======================================================================
	// DROP TARGETS — delegated listeners on the sidebar
	// ======================================================================

	function bindSidebarDropTargets( sidebar ) {

		// dragstart (capture phase) — block folder <li> drags when organize mode
		// is off. When ON, dragstart propagates and folders-reorder.js handles it.
		sidebar.addEventListener( 'dragstart', function ( e ) {
			if ( ! sidebar.classList.contains( 'roci-organize-mode' ) ) {
				e.preventDefault();
			}
		}, true );

		// dragenter / dragover — highlight target, allow drop
		function onDragOver( e ) {
			// Folder-reorder drags are handled entirely by folders-reorder.js.
			// Skip so is-drop-target is never added to folder rows during organize mode.
			if ( sidebar.classList.contains( 'roci-organize-mode' ) ) {
				return;
			}
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			// __all__ is not a valid drop destination.
			if ( target.dataset.term === '__all__' ) {
				return;
			}
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			target.classList.add( 'is-drop-target' );
		}

		sidebar.addEventListener( 'dragenter', onDragOver );
		sidebar.addEventListener( 'dragover',  onDragOver );

		// dragleave — remove highlight; gate on relatedTarget to suppress
		// child-element re-entry firing that would cause flicker.
		sidebar.addEventListener( 'dragleave', function ( e ) {
			var target = e.target.closest( '.roci-folder-item' );
			if ( ! target ) {
				return;
			}
			// Only remove the class when the pointer has genuinely left the item
			// (not just moved into a child element inside it).
			if ( e.relatedTarget && target.contains( e.relatedTarget ) ) {
				return;
			}
			target.classList.remove( 'is-drop-target' );
		} );

		// drop — execute the move
		sidebar.addEventListener( 'drop', function ( e ) {
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

			// Parse the drag payload.
			var raw = e.dataTransfer.getData( 'text/plain' );
			if ( ! raw ) {
				return;
			}
			var payload;
			try { payload = JSON.parse( raw ); } catch ( err ) { return; }

			var attachmentId = payload.id;
			var sourceTerms  = Array.isArray( payload.sourceTerms ) ? payload.sourceTerms : [];

			if ( ! attachmentId ) {
				return;
			}

			// Skip no-ops.
			if ( targetTerm === '__unassigned__' && sourceTerms.length === 0 ) {
				return;
			}
			if ( targetTerm !== '__unassigned__' ) {
				var targetInt = parseInt( targetTerm, 10 );
				if ( sourceTerms.indexOf( targetInt ) !== -1 ) {
					return;
				}
			}

			applyOptimisticCounts( sourceTerms, targetTerm );
			performMove( attachmentId, sourceTerms, targetTerm );
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

	// Sidebar is injected via admin_footer so it may not exist at DOMContentLoaded.
	// Poll until it appears (same pattern as upload-picker.js:172-179).
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
	// WP UPLOAD OVERLAY SUPPRESSION
	// ======================================================================
	//
	// WP's plupload drop zone adds the 'drag-drop' class to body on any
	// dragenter event, which triggers its "Drop files to upload" overlay CSS.
	// It cannot distinguish an OS file drag from our internal thumbnail drag.
	// Capture-phase listeners (third arg = true) run before WP's bubble-phase
	// listeners, so we strip 'drag-drop' off body before WP can restore it.
	// The CSS in _admin-folders-dragdrop.scss hides the overlay elements as
	// a belt-and-suspenders fallback.

	document.addEventListener( 'dragstart', function () {
		document.body.classList.add( 'roci-any-internal-drag' );
	}, true );

	document.addEventListener( 'dragend', function () {
		document.body.classList.remove( 'roci-any-internal-drag' );
	}, true );

	document.addEventListener( 'dragenter', function () {
		if ( document.body.classList.contains( 'roci-any-internal-drag' ) ) {
			document.body.classList.remove( 'drag-drop' );
		}
	}, true );

	document.addEventListener( 'dragover', function () {
		if ( document.body.classList.contains( 'roci-any-internal-drag' ) ) {
			document.body.classList.remove( 'drag-drop' );
		}
	}, true );

} )();
