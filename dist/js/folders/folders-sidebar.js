/**
 * Folder Sidebar — click handling, chevron toggle, grid-view filter dispatch
 *
 * List view (upload.php?mode=list, edit.php?post_type=page):
 *   Sidebar links navigate normally; PHP renders is-active on the next load.
 *
 * Grid view (upload.php without mode=list):
 *   Links are intercepted. A roci:sidebarFilter custom event is dispatched
 *   and media-folder-filter.js updates the Backbone model to trigger the
 *   AJAX refetch — no page navigation occurs.
 *
 * Chevron click toggles child list visibility without triggering the link.
 * In-session only; page refresh resets to collapsed default (Pass 1).
 *
 * Version: 1.0.1
 * Updated: 2026-05-14
 */

( function () {

	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		var sidebar = document.getElementById( 'roci-folders-sidebar' );
		if ( ! sidebar ) {
			return;
		}

		// Grid view: upload.php without ?mode=list.
		var isUpload   = window.location.pathname.indexOf( 'upload.php' ) !== -1;
		var isListMode = window.location.search.indexOf( 'mode=list' ) !== -1;
		var isGridView = isUpload && ! isListMode;

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

		// ── Active state ───────────────────────────────────────────────────
		// Used in grid view only; list view relies on PHP-rendered is-active.

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
			// Unassigned and All have no matching option; reset to "All Folders".
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

			// Chevron — toggle children, stop propagation so the row link
			// is not also triggered.
			var chevron = e.target.closest( '.roci-chevron' );
			if ( chevron ) {
				e.preventDefault();
				e.stopPropagation();
				toggleItem( chevron.closest( '.roci-folder-item' ) );
				return;
			}

			// Folder link — apply filter.
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
			// List view: let the default href navigation happen;
			// PHP will render the correct is-active on the next page load.
		} );

	} );

} )();
