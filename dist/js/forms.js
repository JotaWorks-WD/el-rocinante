/**
 * Forms — Front-end JS (ENHANCEMENT ONLY)
 *
 * Drives template-parts/form.php. Every form works without this file: it
 * posts, validates and confirms on the server, and the part's autofocus puts
 * focus on the error summary or the success message. This script only:
 *
 *   1. marks the submission as coming from a browser that ran JavaScript
 *      (roci_js = 1) — so a missing Turnstile token reads as "complete the
 *      check", not as a no-JS visitor (who is held instead);
 *   2. moves focus to [data-roci-focus] (the error summary or the success
 *      message) where the browser did not honour autofocus;
 *   3. makes each error-summary link FOCUS its field, not just scroll to it;
 *   4. stops a double submit: the button is disabled for the round trip.
 *
 * Registered by el_rocinante_enqueue_assets() (footer) and enqueued only by
 * the form part, so a page without a form never requests it. Hand-written,
 * no dependencies. Scoped to .roci-form.
 *
 * File:    forms.js
 * Version: 1.0.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

( function () {
  'use strict';

  function init() {
    var forms = document.querySelectorAll( '.roci-form' );

    Array.prototype.forEach.call( forms, function ( root ) {

      // 1. JavaScript ran.
      var js = root.querySelector( 'input[name="roci_js"]' );
      if ( js ) {
        js.value = '1';
      }

      // 2. Focus the summary or the success message, once.
      var target = root.querySelector( '[data-roci-focus]' );
      if ( target && document.activeElement !== target ) {
        target.focus();
      }

      // 3. Summary links focus their field.
      Array.prototype.forEach.call( root.querySelectorAll( '.roci-form__errors-list a[href^="#"]' ), function ( link ) {
        link.addEventListener( 'click', function ( event ) {
          var field = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
          if ( field ) {
            event.preventDefault();
            field.focus();
          }
        } );
      } );

      // 4. One submit per round trip.
      if ( 'FORM' === root.tagName ) {
        root.addEventListener( 'submit', function ( event ) {
          if ( root.getAttribute( 'data-roci-sending' ) ) {
            event.preventDefault();
            return;
          }
          root.setAttribute( 'data-roci-sending', '1' );
          var button = root.querySelector( 'button[type="submit"]' );
          if ( button ) {
            // After this tick, so the submission itself is not cancelled.
            window.setTimeout( function () {
              button.disabled = true;
              button.setAttribute( 'aria-disabled', 'true' );
            }, 0 );
          }
        } );

        // Back / forward cache: a page restored from it keeps the disabled
        // button, so re-arm the form.
        window.addEventListener( 'pageshow', function ( event ) {
          if ( ! event.persisted ) {
            return;
          }
          root.removeAttribute( 'data-roci-sending' );
          var button = root.querySelector( 'button[type="submit"]' );
          if ( button ) {
            button.disabled = false;
            button.removeAttribute( 'aria-disabled' );
          }
        } );
      }
    } );
  }

  if ( 'loading' === document.readyState ) {
    document.addEventListener( 'DOMContentLoaded', init );
  } else {
    init();
  }
} )();
