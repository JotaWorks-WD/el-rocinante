/**
 * Folder Sidebar — collapse toggle, chevron toggle, grid-view filter dispatch
 *
 * List view (upload.php?mode=list, edit.php?post_type=page):
 *   Sidebar links navigate normally; PHP renders is-active on next load.
 *
 * Grid view (upload.php without mode=list):
 *   Links are intercepted; roci:sidebarFilter dispatched so
 *   media-folder-filter.js updates the Backbone model via AJAX.
 *
 * Collapse toggle: edge arrow in the sidebar header collapses the sidebar to
 * 36px and expands #wpcontent. CSS transition handles the width animation.
 * State resets on page refresh (persistence added in Pass 2.2).
 *
 * File:    dist/js/folders/folders-sidebar.js
 * Version: 1.1.0
 * Updated: 2026-05-14
 */

( function () {

	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		var sidebar = document.getElementById( 'roci-folders-sidebar' );
		if ( ! sidebar ) {
			return;
		}

		var toggle = document.getElementById( 'roci-sidebar-toggle' );

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

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var nowCollapsed = ! document.documentElement.classList.contains( 'roci-sidebar-is-collapsed' );
				applyCollapsed( nowCollapsed );
			} );
		}

		// ── Chevron toggle ─────────────────────────────────────────────────

		function toggleItem( item ) {
			var children = item.querySelector( '.roci-folder-children' );
			if ( ! children ) {
				return;
			}
			if ( item.classList.contains( 'is-open' ) ) {
				item.classList.remove( 'is-open' );
				children.style.display = 'none';
			} else {
				item.classList.add( 'is-open' );
				children.style.display = 'block';
			}
		}

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
