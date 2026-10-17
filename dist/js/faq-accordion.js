/**
 * FAQ Accordion — Front-end JS
 *
 * Drives template-parts/faq.php. On click a button toggles its panel: sets
 * aria-expanded, toggles the hidden attribute, and animates the panel height
 * between 0 and scrollHeight. One panel open at a time within a given
 * .faq-accordion container; several containers on one page work independently.
 *
 * Height is released back to auto once an opening transition finishes, so later
 * reflow (wrapping text, late-loading images) is not clipped.
 *
 * THE PARENT'S ONE FRONT-END SCRIPT. Registered by el_rocinante_enqueue_assets()
 * (footer) and enqueued only by template-parts/faq.php when it renders, so a
 * page without an accordion never requests it. Hand-written, no dependencies.
 *
 * SCOPED TO .roci-faq. It binds only the parent part's markup, so a child's own
 * FAQ markup (and a child's own accordion script) is never bound twice.
 *
 * The script queries two selectors (.roci-faq .faq-accordion,
 * .faq-accordion__button) and touches three attributes (aria-expanded,
 * aria-controls, hidden) plus inline style.height. The ARIA contract is in the
 * part's docblock.
 *
 * File:    faq-accordion.js
 * Version: 1.0.0
 * Updated: 2026-10-17
 *
 * @package ElRocinante
 */

( function () {
  'use strict';

  // ⚠ MUST STAY ABOVE THE PANEL'S HEIGHT TRANSITION — --t-250 (250ms) in
  // Build/scss/components/_faq.scss. Lengthen that transition past 400ms and
  // raise this in the same change, or the cleanup fires mid-animation.
  var TRANSITION_FALLBACK_MS = 400;

  /**
   * Run `done` when the panel's height transition ends, or after the fallback
   * timeout, whichever comes first — once.
   *
   * ⚠ THE TIMER IS LOAD-BEARING. Under prefers-reduced-motion the partial sets
   * transition: none, so transitionend never fires; without the timer an
   * opened panel would never get height: auto and a closed one would never
   * regain hidden.
   */
  function afterTransition( panel, done ) {
    var finished = false;
    var timer;

    function finish() {
      if ( finished ) return;
      finished = true;
      panel.removeEventListener( 'transitionend', onEnd );
      clearTimeout( timer );
      done();
    }

    function onEnd( event ) {
      if ( event.target !== panel || event.propertyName !== 'height' ) return;
      finish();
    }

    panel.addEventListener( 'transitionend', onEnd );
    timer = setTimeout( finish, TRANSITION_FALLBACK_MS );
  }

  function initAccordion( accordion ) {
    var buttons = accordion.querySelectorAll( '.faq-accordion__button' );
    if ( ! buttons.length ) return;

    function panelFor( button ) {
      var id = button.getAttribute( 'aria-controls' );
      return id ? document.getElementById( id ) : null;
    }

    function isOpen( button ) {
      return button.getAttribute( 'aria-expanded' ) === 'true';
    }

    function openPanel( button ) {
      var panel = panelFor( button );
      if ( ! panel ) return;

      button.setAttribute( 'aria-expanded', 'true' );
      panel.hidden = false;

      // Pin to 0 before the first paint so there is a height to animate from.
      panel.style.height = '0px';

      requestAnimationFrame( function () {
        requestAnimationFrame( function () {
          panel.style.height = panel.scrollHeight + 'px';
        } );
      } );

      afterTransition( panel, function () {
        // Guard: the item may have been closed again mid-transition.
        if ( isOpen( button ) ) {
          panel.style.height = 'auto';
        }
      } );
    }

    function closePanel( button ) {
      var panel = panelFor( button );
      if ( ! panel ) return;

      button.setAttribute( 'aria-expanded', 'false' );

      // 'auto' will not animate — resolve it to a pixel height first.
      panel.style.height = panel.scrollHeight + 'px';

      requestAnimationFrame( function () {
        requestAnimationFrame( function () {
          panel.style.height = '0px';
        } );
      } );

      afterTransition( panel, function () {
        // Guard: the item may have been reopened mid-transition.
        if ( ! isOpen( button ) ) {
          panel.hidden = true;
          panel.style.height = '';
        }
      } );
    }

    Array.prototype.forEach.call( buttons, function ( button ) {
      button.addEventListener( 'click', function () {
        var wasOpen = isOpen( button );

        // One open panel at a time, scoped to this container only.
        Array.prototype.forEach.call( buttons, function ( other ) {
          if ( other !== button && isOpen( other ) ) {
            closePanel( other );
          }
        } );

        if ( wasOpen ) {
          closePanel( button );
        } else {
          openPanel( button );
        }
      } );
    } );
  }

  // Footer-loaded, after the markup, so there is nothing to wait for.
  Array.prototype.forEach.call( document.querySelectorAll( '.roci-faq .faq-accordion' ), initAccordion );

}() );
