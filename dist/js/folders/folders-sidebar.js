/**
 * Folder Sidebar — collapse toggle, localStorage persistence, chevron toggle,
 * grid-view filter dispatch, new-folder tree injection
 *
 * List view (upload.php?mode=list, or upload.php with no explicit mode param
 *   when the user's stored WP preference is list view):
 *   Sidebar links navigate normally; PHP renders is-active on next load.
 *
 * Grid view (upload.php?mode=grid, or upload.php with no explicit mode param
 *   when the user's stored WP preference is grid view):
 *   Links are intercepted; roci:sidebarFilter dispatched so
 *   media-folder-filter.js updates the Backbone model via AJAX.
 *
 * Collapse state is persisted to localStorage (survives tab close):
 *   roci_sidebar_collapsed_media / _pages
 * Expanded-folder state is persisted to sessionStorage (resets on tab close,
 * survives within-tab navigation and refresh):
 *   roci_folder_expanded_media / _pages
 * The collapsed class is applied to
 * <html> by an early inline script in admin_head before first paint so
 * there is no visible flicker.
 *
 * After roci:folderCreated, the new folder node is injected directly into
 * the sidebar tree at the correct alphabetical position. If the folder has
 * a parent, the parent is upgraded to a branch node and auto-expanded.
 *
 * Public cross-script helpers (for folders-dragdrop.js count updates):
 *   window.rociIncrementSidebarCount( termKey ) — increment a badge by 1
 *   window.rociDecrementSidebarCount( termKey ) — decrement a badge by 1 (floor 0)
 *   termKey accepts an integer term ID or the sentinels '__all__' / '__unassigned__'.
 *
 * File:    dist/js/folders/folders-sidebar.js
 * Version: 2.8.1
 * Updated: 2026-05-17
 */

( function () {

	'use strict';

	// ── Storage keys (screen-scoped) ──────────────────────────────────────
	var screenKey      = ( typeof rociSidebar !== 'undefined' && rociSidebar.screenKey ) ? rociSidebar.screenKey : 'media';
	var lsKeyCollapsed = 'roci_sidebar_collapsed_' + screenKey; // localStorage  — persists across tabs/sessions
	var ssKeyExpanded  = 'roci_folder_expanded_'   + screenKey; // sessionStorage — resets on tab close

	// ── Safe localStorage wrappers (collapsed state) ──────────────────────
	function lsGet( key ) {
		try { return localStorage.getItem( key ); } catch ( e ) { return null; }
	}
	function lsSet( key, val ) {
		try { localStorage.setItem( key, val ); } catch ( e ) {}
	}

	// ── Safe sessionStorage wrappers (expanded state) ─────────────────────
	function ssGet( key ) {
		try { return sessionStorage.getItem( key ); } catch ( e ) { return null; }
	}
	function ssSet( key, val ) {
		try { sessionStorage.setItem( key, val ); } catch ( e ) {}
	}

	document.addEventListener( 'DOMContentLoaded', function () {

		var sidebar = document.getElementById( 'roci-folders-sidebar' );
		if ( ! sidebar ) {
			return;
		}

		var toggle    = document.getElementById( 'roci-sidebar-toggle' );
		var folderKey = sidebar.dataset.folderKey;

		// Base URL derived from the "All Files" virtual entry so it naturally
		// includes any mode=list param that PHP baked into the link.
		var allFilesLink = sidebar.querySelector( '[data-term="__all__"] a' );
		var baseUrl      = allFilesLink ? allFilesLink.href : '';

		var isUpload = window.location.pathname.indexOf( 'upload.php' ) !== -1;
		var isGridView = (function () {
			if ( ! isUpload ) { return false; }
			var search = window.location.search;
			if ( search.indexOf( 'mode=grid' ) !== -1 ) { return true; }
			if ( search.indexOf( 'mode=list' ) !== -1 ) { return false; }
			// No explicit mode param — WP may serve list view from stored user preference
			// without adding ?mode=list to the URL. The list table is present in DOM;
			// the Backbone grid frame is not. Use that to detect the actual mode.
			return ! document.querySelector( '.wp-list-table.media' );
		}());

		// ── Collapse toggle ────────────────────────────────────────────────

		function applyCollapsed( collapsed ) {
			var html      = document.documentElement;
			var icon      = toggle ? toggle.querySelector( '.dashicons' ) : null;
			var wpcontent = document.getElementById( 'wpcontent' );

			if ( collapsed ) {
				// Clear inline width/margin so the collapsed-state CSS rules take over.
				// The resize IIFE sets both as inline styles, which would otherwise
				// win over the html.roci-sidebar-is-collapsed CSS selectors.
				sidebar.style.width = '';
				if ( wpcontent ) { wpcontent.style.marginLeft = ''; }

				html.classList.add( 'roci-sidebar-is-collapsed' );
				if ( icon ) {
					icon.classList.remove( 'dashicons-arrow-left-alt2' );
					icon.classList.add( 'dashicons-arrow-right-alt2' );
				}
				if ( toggle ) {
					toggle.setAttribute( 'aria-label',    'Expand folder sidebar' );
					toggle.setAttribute( 'aria-expanded', 'false' );
				}
			} else {
				// Restore the saved resize width before un-collapsing so the sidebar
				// snaps to the user's saved size rather than the CSS default width.
				// Storage key and bounds must match the resize IIFE below.
				try {
					var savedWidth = localStorage.getItem( 'roci-folders-sidebar-width' );
					if ( savedWidth ) {
						var w = parseInt( savedWidth, 10 );
						if ( w >= 240 && w <= 480 ) {
							sidebar.style.width = w + 'px';
							if ( wpcontent ) {
								var sidebarLeft = parseInt( window.getComputedStyle( sidebar ).left, 10 ) || 0;
								wpcontent.style.marginLeft = ( sidebarLeft + w ) + 'px';
							}
						}
					}
				} catch ( e ) {}

				html.classList.remove( 'roci-sidebar-is-collapsed' );
				if ( icon ) {
					icon.classList.remove( 'dashicons-arrow-right-alt2' );
					icon.classList.add( 'dashicons-arrow-left-alt2' );
				}
				if ( toggle ) {
					toggle.setAttribute( 'aria-label',    'Collapse folder sidebar' );
					toggle.setAttribute( 'aria-expanded', 'true' );
				}
			}
		}

		// Sync icon to whatever class the early inline script applied to <html>.
		applyCollapsed( document.documentElement.classList.contains( 'roci-sidebar-is-collapsed' ) );

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var nowCollapsed = ! document.documentElement.classList.contains( 'roci-sidebar-is-collapsed' );
				applyCollapsed( nowCollapsed );
				lsSet( lsKeyCollapsed, nowCollapsed ? '1' : '0' );
			} );
		}

		// ── Chevron / open-folder persistence ─────────────────────────────

		function getOpenTermIds() {
			var ids = [];
			sidebar.querySelectorAll( '.roci-folder-item.is-open' ).forEach( function ( el ) {
				var id = el.dataset.term;
				if ( id && id !== '__all__' && id !== '__unassigned__' ) {
					ids.push( id );
				}
			} );
			return ids;
		}

		function persistExpanded() {
			ssSet( ssKeyExpanded, JSON.stringify( getOpenTermIds() ) );
		}

		function openChildren( item ) {
			var children = item.querySelector( '.roci-folder-children' );
			if ( ! children ) {
				return;
			}
			item.classList.add( 'is-open' );
			children.style.display = 'block';
		}

		function closeChildren( item ) {
			var children = item.querySelector( '.roci-folder-children' );
			if ( ! children ) {
				return;
			}
			item.classList.remove( 'is-open' );
			children.style.display = 'none';
		}

		function toggleItem( item ) {
			if ( item.classList.contains( 'is-open' ) ) {
				closeChildren( item );
			} else {
				openChildren( item );
			}
			persistExpanded();
		}

		// Restore open-folder state saved from the previous page load.
		function restoreExpandedFolders() {
			var raw = ssGet( ssKeyExpanded );
			if ( ! raw ) {
				return;
			}
			var ids;
			try { ids = JSON.parse( raw ); } catch ( e ) { return; }
			if ( ! Array.isArray( ids ) ) {
				return;
			}
			ids.forEach( function ( id ) {
				var item = sidebar.querySelector( '[data-term="' + id + '"]' );
				if ( item ) {
					openChildren( item );
				}
			} );
		}

		restoreExpandedFolders();

		// ── Folder search bar ──────────────────────────────────────────────────

		function initFolderSearch() {
			var input = sidebar.querySelector( '.roci-folder-search__input' );
			if ( ! input ) { return; }
			input.addEventListener( 'input', function () {
				var q = this.value.toLowerCase();
				sidebar.querySelectorAll( '.roci-folder-item:not(.roci-item-virtual)' ).forEach( function ( item ) {
					var link = item.querySelector( '.roci-folder-link' );
					var name = link ? link.firstChild.textContent.toLowerCase() : '';
					item.style.display = ( ! q || name.indexOf( q ) !== -1 ) ? '' : 'none';
				} );
			} );
		}

		initFolderSearch();

		// ── Parent-direct-hover ────────────────────────────────────────────
		//
		// Applies .is-parent-direct-hover to a parent item only when the
		// cursor is over that item's own .roci-item-row, not a descendant's.
		// Because closest() walks up from the actual target, hovering a child
		// row resolves to the child item — which lacks .has-children — so the
		// parent never receives the class incorrectly.

		function clearParentDirectHover() {
			sidebar.querySelectorAll( '.is-parent-direct-hover' ).forEach( function ( el ) {
				el.classList.remove( 'is-parent-direct-hover' );
			} );
		}

		sidebar.addEventListener( 'mouseover', function ( e ) {
			var row = e.target.closest( '.roci-item-row' );
			clearParentDirectHover();
			if ( ! row ) { return; }
			var item = row.closest( '.roci-folder-item' );
			if ( item && item.classList.contains( 'has-children' ) ) {
				item.classList.add( 'is-parent-direct-hover' );
			}
		} );

		sidebar.addEventListener( 'mouseleave', clearParentDirectHover );

		// ── Active state (grid view only) ──────────────────────────────────

		function setActiveItem( item ) {
			sidebar.querySelectorAll( '.roci-folder-item' ).forEach( function ( el ) {
				el.classList.remove( 'is-active' );
			} );
			item.classList.add( 'is-active' );
		}

		// ── Sync toolbar dropdown to sidebar selection ─────────────────────

		function syncSelectToTerm( termId ) {
			var sel = document.getElementById( 'roci-media-folder-filter' );
			if ( ! sel ) {
				return;
			}
			if ( termId === '__all__' || termId === '__unassigned__' ) {
				sel.value = '';
			} else {
				sel.value = String( termId );
			}
		}

		// ── Grid-view filter dispatch ──────────────────────────────────────

		function dispatchSidebarFilter( termId ) {
			document.dispatchEvent( new CustomEvent( 'roci:sidebarFilter', {
				detail: { termId: String( termId ) }
			} ) );
		}

		// ── New folder: replace sidebar tree from server HTML ──────────────
		//
		// The AJAX response includes the full server-rendered tree inner HTML so
		// position, icons, and markup always match PHP. JS only needs to restore
		// the expand/active state that was visible before the swap.

		document.addEventListener( 'roci:folderCreated', function ( e ) {
			var detail   = e.detail || {};
			var treeHtml = detail.tree_html;
			var tree     = sidebar.querySelector( '.roci-folder-tree' );
			if ( ! tree || ! treeHtml ) {
				return;
			}

			// Save visible state before wiping the tree.
			var activeTermId = '';
			var activeItem   = sidebar.querySelector( '.roci-folder-item.is-active' );
			if ( activeItem ) {
				activeTermId = activeItem.dataset.term || '';
			}
			var expandedIds = getOpenTermIds();

			// Swap tree contents with server-rendered HTML.
			tree.innerHTML = treeHtml;

			// Restore active highlight.
			if ( activeTermId ) {
				var matchActive = tree.querySelector( '[data-term="' + activeTermId + '"]' );
				if ( matchActive ) {
					matchActive.classList.add( 'is-active' );
				}
			}

			// Restore open/expanded folders.
			expandedIds.forEach( function ( id ) {
				var item = tree.querySelector( '[data-term="' + id + '"]' );
				if ( item ) {
					openChildren( item );
				}
			} );

			// Auto-expand the new folder's parent if it has one.
			if ( detail.term && detail.term.parent ) {
				var parentItem = tree.querySelector( '[data-term="' + detail.term.parent + '"]' );
				if ( parentItem ) {
					openChildren( parentItem );
				}
			}

			persistExpanded();
		} );

		// ── Event delegation ───────────────────────────────────────────────

		sidebar.addEventListener( 'click', function ( e ) {

			var chevron = e.target.closest( '.roci-chevron' );
			if ( chevron ) {
				e.preventDefault();
				e.stopPropagation();
				toggleItem( chevron.closest( '.roci-folder-item' ) );
				return;
			}

			var link = e.target.closest( '.roci-item-row a' );
			if ( ! link ) {
				return;
			}

			var item   = link.closest( '.roci-folder-item' );
			var termId = item ? item.dataset.term : null;
			if ( ! termId ) {
				return;
			}

			if ( isGridView ) {
				e.preventDefault();
				setActiveItem( item );
				syncSelectToTerm( termId );
				dispatchSidebarFilter( termId );
			}
			// List view: let href navigate; PHP renders is-active on next load.
		} );

		// ── List-view: active-border class + auto-submit on folder dropdown change ─

		if ( ! isGridView ) {
			var folderSelect = document.getElementById( 'roci-media-folder-filter' );
			if ( folderSelect ) {
				if ( folderSelect.value && folderSelect.value !== '0' ) {
					folderSelect.classList.add( 'is-active-filter' );
				}
				folderSelect.addEventListener( 'change', function () {
					this.classList.toggle( 'is-active-filter', this.value !== '' && this.value !== '0' );
					var form = document.getElementById( 'posts-filter' );
					if ( form ) {
						form.submit();
					}
				} );
			}

			// Generic CPT folder filter auto-submit (Pages, Posts, and future CPTs).
			// PHP passes filterSelectId via rociSidebar for all registered post types.
			var cptFilterId = ( typeof rociSidebar !== 'undefined' && rociSidebar.filterSelectId ) ? rociSidebar.filterSelectId : null;
			var cptSelect = cptFilterId ? document.getElementById( cptFilterId ) : null;
			if ( cptSelect ) {
				if ( cptSelect.value && cptSelect.value !== '0' ) {
					cptSelect.classList.add( 'is-active-filter' );
				}
				cptSelect.addEventListener( 'change', function () {
					this.classList.toggle( 'is-active-filter', this.value !== '' && this.value !== '0' );
					var form = document.getElementById( 'posts-filter' );
					if ( form ) {
						form.submit();
					}
				} );
			}
		}

	} );

	// -----------------------------------------------------------------
	// Folder count updates on attachment delete
	// -----------------------------------------------------------------

	function bindAttachmentDeleteListener () {
		if ( ! window.wp || ! wp.media || ! wp.media.model || ! wp.media.model.Attachment ) {
			return false;
		}
		if ( wp.media.model.Attachment.prototype._rociDeletePatched ) {
			return true;
		}
		var origDestroy = wp.media.model.Attachment.prototype.destroy;
		wp.media.model.Attachment.prototype.destroy = function () {
			// Capture folder taxonomy before destroy clears the model.
			var capturedFolders = [];
			try {
				var folders = this.get( 'roci_media_folder' );
				if ( Array.isArray( folders ) ) {
					capturedFolders = folders;
				}
			} catch ( e ) {}

			var result = origDestroy.apply( this, arguments );
			if ( result && typeof result.done === 'function' ) {
				result.done( function () {
					handleAttachmentDeleted( capturedFolders );
				} );
			} else if ( result && typeof result.then === 'function' ) {
				result.then( function () {
					handleAttachmentDeleted( capturedFolders );
				} );
			}
			return result;
		};
		wp.media.model.Attachment.prototype._rociDeletePatched = true;
		return true;
	}

	function handleAttachmentDeleted ( capturedFolders ) {
		// Always decrement All Files.
		decrementSidebarCount( '__all__' );

		// Primary path: use captured taxonomy from the destroyed attachment.
		if ( Array.isArray( capturedFolders ) && capturedFolders.length > 0 ) {
			capturedFolders.forEach( function ( termId ) {
				decrementSidebarCount( termId );
				decrementDropdownOption( termId );
			} );

			if ( typeof window.rociForceLibraryRefresh === 'function' ) {
				window.rociForceLibraryRefresh();
			}
			return;
		}

		// Fallback: empty captured → either unassigned or taxonomy not exposed.
		// Try URL/props to disambiguate.
		var folderTerm = null;
		var noFolder   = false;

		var params = new URLSearchParams( window.location.search );
		folderTerm = params.get( 'roci_media_folder' );
		noFolder   = params.get( 'roci_no_folder' ) === '1';

		// Grid mode: filter lives in wp.media library props, not URL.
		if ( ! folderTerm && ! noFolder && window.wp && wp.media && wp.media.frame ) {
			try {
				var libState = wp.media.frame.state && wp.media.frame.state();
				var lib = libState && libState.get && libState.get( 'library' );
				if ( lib && lib.props ) {
					folderTerm = lib.props.get( 'roci_media_folder' );
					if ( ! folderTerm ) {
						noFolder = lib.props.get( 'roci_no_folder' ) === '1';
					}
				}
			} catch ( e ) {}
		}

		if ( folderTerm ) {
			var termId = parseInt( folderTerm, 10 );
			decrementSidebarCount( termId );
			decrementDropdownOption( termId );
		} else {
			// Empty captured + no filter context = unassigned.
			decrementSidebarCount( '__unassigned__' );
		}

		if ( typeof window.rociForceLibraryRefresh === 'function' ) {
			window.rociForceLibraryRefresh();
		}
	}

	function decrementSidebarCount ( termKey ) {
		var el = document.querySelector( '[data-term="' + termKey + '"] .roci-folder-count' );
		if ( ! el ) {
			return;
		}
		var match = el.textContent.match( /\((\d+)\)/ );
		if ( ! match ) {
			return;
		}
		var next = Math.max( 0, parseInt( match[ 1 ], 10 ) - 1 );
		el.textContent = '(' + next + ')';
	}

	function decrementDropdownOption ( termId ) {
		var option = document.querySelector( '#roci-media-folder-filter option[value="' + termId + '"]' );
		if ( ! option ) {
			return;
		}
		var match = option.textContent.match( /\((\d+)\)$/ );
		if ( ! match ) {
			return;
		}
		var next = Math.max( 0, parseInt( match[ 1 ], 10 ) - 1 );
		option.textContent = option.textContent.replace( /\((\d+)\)$/, '(' + next + ')' );
	}

	function incrementSidebarCount ( termKey ) {
		var el = document.querySelector( '[data-term="' + termKey + '"] .roci-folder-count' );
		if ( ! el ) {
			return;
		}
		var match = el.textContent.match( /\((\d+)\)/ );
		if ( ! match ) {
			return;
		}
		var next = parseInt( match[ 1 ], 10 ) + 1;
		el.textContent = '(' + next + ')';
	}

	function incrementDropdownOption ( termId ) {
		var option = document.querySelector( '#roci-media-folder-filter option[value="' + termId + '"]' );
		if ( ! option ) {
			return;
		}
		var match = option.textContent.match( /\((\d+)\)$/ );
		if ( ! match ) {
			return;
		}
		var next = parseInt( match[ 1 ], 10 ) + 1;
		option.textContent = option.textContent.replace( /\((\d+)\)$/, '(' + next + ')' );
	}

	function handleAttachmentUploaded ( attachment ) {
		incrementSidebarCount( '__all__' );

		var picker = document.querySelector( '.roci-upload-picker__select' );
		if ( picker && picker.value ) {
			var termId = parseInt( picker.value, 10 );
			if ( termId ) {
				incrementSidebarCount( termId );
				incrementDropdownOption( termId );
				return;
			}
		}
		// No folder selected (or no picker present) — file lands as unassigned.
		incrementSidebarCount( '__unassigned__' );
	}

	window.rociHandleAttachmentUploaded = handleAttachmentUploaded;
	window.rociIncrementSidebarCount    = incrementSidebarCount;
	window.rociDecrementSidebarCount    = decrementSidebarCount;

	( function () {
		if ( bindAttachmentDeleteListener() ) {
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( bindAttachmentDeleteListener() || attempts > 50 ) {
				clearInterval( interval );
			}
		}, 100 );
	} )();

} )();

// ── Sidebar resize handle ─────────────────────────────────────────────────────
// Separate IIFE — kept isolated for easy future extraction to its own file.

( function () {
	'use strict';

	var STORAGE_KEY = 'roci-folders-sidebar-width';
	var MIN_WIDTH   = 240;
	var MAX_WIDTH   = 480;

	var sidebar = document.getElementById( 'roci-folders-sidebar' );
	if ( ! sidebar ) { return; }

	var handle = sidebar.querySelector( '.roci-sidebar-resize-handle' );
	if ( ! handle ) { return; }

	// Restore saved width on load.
	( function () {
		try {
			var savedWidth = localStorage.getItem( STORAGE_KEY );
			if ( savedWidth ) {
				var w = parseInt( savedWidth, 10 );
				if ( w >= MIN_WIDTH && w <= MAX_WIDTH ) {
					sidebar.style.width = w + 'px';
					updateContentMargin( w );
				}
			}
		} catch ( e ) {}
	} )();

	var isDragging = false;
	var startX     = 0;
	var startWidth = 0;

	handle.addEventListener( 'mousedown', function ( e ) {
		isDragging = true;
		startX     = e.clientX;
		startWidth = sidebar.offsetWidth;
		handle.classList.add( 'is-dragging' );
		document.body.style.cursor     = 'col-resize';
		document.body.style.userSelect = 'none';
		e.preventDefault();
	} );

	document.addEventListener( 'mousemove', function ( e ) {
		if ( ! isDragging ) { return; }
		var delta    = e.clientX - startX;
		var newWidth = Math.max( MIN_WIDTH, Math.min( MAX_WIDTH, startWidth + delta ) );
		sidebar.style.width = newWidth + 'px';
		updateContentMargin( newWidth );
	} );

	document.addEventListener( 'mouseup', function () {
		if ( ! isDragging ) { return; }
		isDragging = false;
		handle.classList.remove( 'is-dragging' );
		document.body.style.cursor     = '';
		document.body.style.userSelect = '';
		try {
			localStorage.setItem( STORAGE_KEY, sidebar.offsetWidth );
		} catch ( err ) {}
	} );

	// Shift #wpcontent so it stays clear of the resized sidebar.
	// Reads sidebar.style.left at call time so it works for both the
	// normal (160px) and folded (36px) WP admin nav states.
	function updateContentMargin( sidebarWidth ) {
		var wpcontent = document.getElementById( 'wpcontent' );
		if ( ! wpcontent ) { return; }
		var sidebarLeft = parseInt( window.getComputedStyle( sidebar ).left, 10 ) || 0;
		wpcontent.style.marginLeft = ( sidebarLeft + sidebarWidth ) + 'px';
	}

} )();
