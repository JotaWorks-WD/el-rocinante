/**
 * Folder Sidebar — collapse toggle, localStorage persistence, chevron toggle,
 * grid-view filter dispatch, new-folder tree injection
 *
 * List view (upload.php?mode=list, edit.php?post_type=page):
 *   Sidebar links navigate normally; PHP renders is-active on next load.
 *
 * Grid view (upload.php without mode=list):
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
 * File:    dist/js/folders/folders-sidebar.js
 * Version: 2.2.0
 * Updated: 2026-05-14
 */

( function () {

	'use strict';

	// ── Storage keys (screen-scoped) ──────────────────────────────────────
	var screenKey      = ( typeof rociSidebar !== 'undefined' && rociSidebar.screenKey ) ? rociSidebar.screenKey : 'media';
	var lsKeyCollapsed = 'roci_sidebar_collapsed_' + screenKey; // localStorage  — persists across tabs/sessions
	var lsKeyExpanded  = 'roci_folder_expanded_'   + screenKey; // sessionStorage — resets on tab close

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

		// Grid view: upload.php without ?mode=list.
		var isUpload   = window.location.pathname.indexOf( 'upload.php' ) !== -1;
		var isListMode = window.location.search.indexOf( 'mode=list' ) !== -1;
		var isGridView = isUpload && ! isListMode;

		// ── Collapse toggle ────────────────────────────────────────────────

		function applyCollapsed( collapsed ) {
			var html = document.documentElement;
			var icon = toggle ? toggle.querySelector( '.dashicons' ) : null;

			if ( collapsed ) {
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
			ssSet( lsKeyExpanded, JSON.stringify( getOpenTermIds() ) );
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
			var raw = ssGet( lsKeyExpanded );
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

		// ── List-view: auto-submit on folder dropdown change ───────────────

		if ( ! isGridView ) {
			var folderSelect = document.getElementById( 'roci-media-folder-filter' );
			if ( folderSelect ) {
				folderSelect.addEventListener( 'change', function () {
					var form = document.getElementById( 'posts-filter' );
					if ( form ) {
						form.submit();
					}
				} );
			}
		}

	} );

} )();
