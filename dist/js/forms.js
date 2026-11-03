/**
 * Forms — Front-end JS (ENHANCEMENT ONLY)
 *
 * Drives template-parts/form.php. Every form works without this file: it
 * posts to its own page, validates and confirms on the server, and the part's
 * autofocus puts focus on the error summary or the success message. This
 * script only:
 *
 *   1. marks the submission as coming from a browser that ran JavaScript
 *      (roci_js = 1) — so a missing Turnstile token reads as "complete the
 *      check", not as a no-JS visitor (who is held instead);
 *   2. moves focus to [data-roci-focus] (the error summary or the success
 *      message) where the browser did not honour autofocus;
 *   3. makes each error-summary link FOCUS its field, not just scroll to it;
 *   4. SUBMITS IN THE BACKGROUND (1.1.0, El Rocinante 8.0.1): fetch() to
 *      POST roci/v1/form/{id} (inc/forms/forms-rest.php — the same server
 *      pipeline as the page POST), then draws the result IN PLACE, with the
 *      part's own classes and ids:
 *        errors   the summary (first in the form), each field's message above
 *                 its control, aria-invalid + aria-describedby (help + error)
 *                 set on the control and cleared from fixed ones; the values
 *                 stay as typed; Turnstile is reset; focus → the summary
 *        success  the form is replaced by the success message (the part's
 *                 "sent" markup); focus → the message
 *      Focus moves without the browser's own scroll; the target is then
 *      brought into view only if it is off screen (smoothly unless reduced
 *      motion). No reload, so no page transition runs.
 *   5. while sending: the button is disabled (and aria-disabled), its label
 *      reads "Sending…", the form is aria-busy and a polite status line says
 *      "Sending…" for screen readers; a second submit is ignored.
 *
 * FALLBACK — THE PAGE POST, UNCHANGED: no fetch / FormData / endpoint, a
 * form without its root id or roci_form_id input, a network failure, or a
 * reply that is not the endpoint's JSON (a 5xx, a firewall page, a 404) → the
 * form is submitted the old way (form.submit()), which posts, reloads and
 * lands on #roci-form-{id}. A control without the part's
 * roci-form-{id}-{field} id still gets its message — in the summary only.
 *
 * Config: window.rociForms = { endpoint, sending, errorPrefix }
 * (wp_localize_script in the parent's functions.php). Registered in the
 * footer and enqueued only by the form part. Hand-written, no dependencies.
 * Scoped to .roci-form.
 *
 * File:    forms.js
 * Version: 1.1.0
 * Updated: 2026-11-03
 *
 * @package ElRocinante
 */

( function () {
  'use strict';

  var cfg = window.rociForms || {};

  var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

  function each( list, fn ) {
    Array.prototype.forEach.call( list, fn );
  }

  function el( tag, className, text ) {
    var node = document.createElement( tag );
    if ( className ) {
      node.className = className;
    }
    if ( text ) {
      node.textContent = text;
    }
    return node;
  }

  // Focus without the browser's jump, then scroll only if it is off screen.
  function focusInPlace( target ) {
    try {
      target.focus( { preventScroll: true } );
    } catch ( e ) {
      target.focus();
    }
    var rect = target.getBoundingClientRect();
    var view = window.innerHeight || document.documentElement.clientHeight;
    if ( rect.top < 0 || rect.bottom > view ) {
      target.scrollIntoView( { block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' } );
    }
  }

  // Summary links focus their field (page-rendered and drawn summaries alike).
  function bindSummaryLinks( root ) {
    each( root.querySelectorAll( '.roci-form__errors-list a[href^="#"]' ), function ( link ) {
      if ( link.getAttribute( 'data-roci-bound' ) ) {
        return;
      }
      link.setAttribute( 'data-roci-bound', '1' );
      link.addEventListener( 'click', function ( event ) {
        var field = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );
        if ( field ) {
          event.preventDefault();
          field.focus();
        }
      } );
    } );
  }

  // ---------------------------------------------------------------- sending --

  function statusLine( root ) {
    var line = root.querySelector( '[data-roci-status]' );
    if ( ! line ) {
      line = el( 'span', 'screen-reader-text' );
      line.setAttribute( 'role', 'status' );
      line.setAttribute( 'data-roci-status', '' );
      var actions = root.querySelector( '.roci-form__actions' ) || root;
      actions.appendChild( line );
    }
    return line;
  }

  function setSending( root, on ) {
    var button = root.querySelector( 'button[type="submit"]' );

    if ( on ) {
      root.setAttribute( 'data-roci-sending', '1' );
      root.setAttribute( 'aria-busy', 'true' );
    } else {
      root.removeAttribute( 'data-roci-sending' );
      root.removeAttribute( 'aria-busy' );
    }

    if ( button ) {
      if ( on ) {
        if ( ! button.hasAttribute( 'data-roci-label' ) ) {
          button.setAttribute( 'data-roci-label', button.textContent );
        }
        if ( cfg.sending ) {
          button.textContent = cfg.sending;
        }
        button.disabled = true;
        button.setAttribute( 'aria-disabled', 'true' );
      } else {
        if ( button.hasAttribute( 'data-roci-label' ) ) {
          button.textContent = button.getAttribute( 'data-roci-label' );
          button.removeAttribute( 'data-roci-label' );
        }
        button.disabled = false;
        button.removeAttribute( 'aria-disabled' );
      }
    }

    if ( cfg.sending ) {
      statusLine( root ).textContent = on ? cfg.sending : '';
    }
  }

  // ----------------------------------------------------------------- errors --

  function clearErrors( root ) {
    var old = root.querySelector( '.roci-form__errors' );
    if ( old ) {
      old.parentNode.removeChild( old );
    }
    each( root.querySelectorAll( '.roci-form__error' ), function ( node ) {
      node.parentNode.removeChild( node );
    } );
    each( root.querySelectorAll( '.roci-form__field--invalid' ), function ( node ) {
      node.classList.remove( 'roci-form__field--invalid' );
    } );
    each( root.querySelectorAll( '[aria-invalid]' ), function ( control ) {
      control.removeAttribute( 'aria-invalid' );
      var ids = ( control.getAttribute( 'aria-describedby' ) || '' ).split( /\s+/ ).filter( function ( id ) {
        return id && id !== control.id + '-error';
      } );
      if ( ids.length ) {
        control.setAttribute( 'aria-describedby', ids.join( ' ' ) );
      } else {
        control.removeAttribute( 'aria-describedby' );
      }
    } );
    root.classList.remove( 'roci-form--has-errors' );
  }

  function showErrors( root, reply ) {
    clearErrors( root );

    var errors = reply.errors || {};
    var notes  = reply.notes || [];
    var list   = el( 'ul', 'roci-form__errors-list' );

    Object.keys( errors ).forEach( function ( fieldId ) {
      var message = String( errors[ fieldId ] );
      var control = document.getElementById( root.id + '-' + fieldId );
      var item    = el( 'li' );

      if ( control ) {
        var field = control.closest( '.roci-form__field' );
        if ( field ) {
          field.classList.add( 'roci-form__field--invalid' );
        }

        // The part's order: label · help · error · control.
        var error  = el( 'p', 'roci-form__error' );
        var prefix = el( 'span', 'screen-reader-text', ( cfg.errorPrefix || 'Error:' ) + ' ' );
        error.id = control.id + '-error';
        error.appendChild( prefix );
        error.appendChild( document.createTextNode( message ) );
        control.parentNode.insertBefore( error, control );

        control.setAttribute( 'aria-invalid', 'true' );
        var ids = [];
        if ( document.getElementById( control.id + '-help' ) ) {
          ids.push( control.id + '-help' );
        }
        ids.push( error.id );
        control.setAttribute( 'aria-describedby', ids.join( ' ' ) );

        var link = el( 'a', '', message );
        link.href = '#' + control.id;
        item.appendChild( link );
      } else {
        item.textContent = message;
      }

      list.appendChild( item );
    } );

    notes.forEach( function ( note ) {
      list.appendChild( el( 'li', '', String( note ) ) );
    } );

    var summary = el( 'div', 'roci-form__errors' );
    summary.id = root.id + '-errors';
    summary.setAttribute( 'tabindex', '-1' );
    summary.setAttribute( 'data-roci-focus', '' );
    summary.appendChild( el( 'h2', 'roci-form__errors-title', reply.summary || '' ) );
    summary.appendChild( list );

    root.insertBefore( summary, root.firstChild );
    root.classList.add( 'roci-form--has-errors' );
    bindSummaryLinks( root );

    // A token is single-use: a fresh check for the next attempt.
    var widget = root.querySelector( '.cf-turnstile' );
    if ( widget && window.turnstile && 'function' === typeof window.turnstile.reset ) {
      try {
        window.turnstile.reset( widget );
      } catch ( e ) {}
    }

    focusInPlace( summary );
  }

  // ---------------------------------------------------------------- success --

  function showSuccess( root, reply ) {
    var sent = el( 'div', root.className );
    sent.classList.remove( 'roci-form--has-errors' );
    sent.classList.add( 'roci-form--sent' );
    sent.id = root.id;

    var classes = reply.classes && reply.classes.success && reply.classes.success.length ? reply.classes.success : [ 'roci-form__success' ];
    var message = el( 'p', classes.join( ' ' ), reply.message || '' );
    message.id = root.id + '-success';
    message.setAttribute( 'tabindex', '-1' );
    message.setAttribute( 'data-roci-focus', '' );
    sent.appendChild( message );

    root.parentNode.replaceChild( sent, root );
    focusInPlace( message );
  }

  // ----------------------------------------------------------------- submit --

  function canFetch( root ) {
    return !! ( window.fetch && window.FormData && cfg.endpoint && root.id && root.querySelector( 'input[name="roci_form_id"]' ) );
  }

  // The page POST, exactly as without JavaScript. submit() fires no submit
  // event, so this handler does not run again.
  function nativeSubmit( root ) {
    root.setAttribute( 'data-roci-native', '1' );
    HTMLFormElement.prototype.submit.call( root );
  }

  function backgroundSubmit( root ) {
    var formId = root.querySelector( 'input[name="roci_form_id"]' ).value;
    var data   = new FormData( root );

    data.set( 'roci_js', '1' );
    data.set( 'roci_source', window.location.href.split( '#' )[ 0 ] );

    setSending( root, true );

    window.fetch( cfg.endpoint + encodeURIComponent( formId ), {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    } ).then( function ( response ) {
      return response.json();
    } ).then( function ( reply ) {
      if ( reply && true === reply.ok ) {
        showSuccess( root, reply );
        return;
      }
      if ( reply && false === reply.ok && ( reply.errors || reply.notes ) ) {
        setSending( root, false );
        showErrors( root, reply );
        return;
      }
      throw new Error( 'roci-forms: unexpected reply' );
    } ).catch( function () {
      nativeSubmit( root );
    } );
  }

  // ------------------------------------------------------------------- init --

  function init() {
    each( document.querySelectorAll( '.roci-form' ), function ( root ) {

      // 1. JavaScript ran.
      var js = root.querySelector( 'input[name="roci_js"]' );
      if ( js ) {
        js.value = '1';
      }

      // 2. Focus the summary or the success message, once (a no-JS reply
      // rendered by the page, where autofocus was not honoured).
      var target = root.querySelector( '[data-roci-focus]' );
      if ( target && document.activeElement !== target ) {
        target.focus();
      }

      // 3. Summary links focus their field.
      bindSummaryLinks( root );

      if ( 'FORM' !== root.tagName ) {
        return;
      }

      // 4 + 5. Submit in the background; one submit per round trip.
      root.addEventListener( 'submit', function ( event ) {
        if ( root.getAttribute( 'data-roci-native' ) ) {
          return;
        }
        if ( root.getAttribute( 'data-roci-sending' ) ) {
          event.preventDefault();
          return;
        }
        if ( canFetch( root ) ) {
          event.preventDefault();
          backgroundSubmit( root );
          return;
        }

        // No fetch: the page POST, with the button disabled after this tick
        // (so the submission itself is not cancelled).
        root.setAttribute( 'data-roci-sending', '1' );
        var button = root.querySelector( 'button[type="submit"]' );
        if ( button ) {
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
        root.removeAttribute( 'data-roci-native' );
        setSending( root, false );
      } );
    } );
  }

  if ( 'loading' === document.readyState ) {
    document.addEventListener( 'DOMContentLoaded', init );
  } else {
    init();
  }
} )();
