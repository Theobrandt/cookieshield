/**
 * CookieShield Banner — front-end consent logic.
 *
 * Relies solely on the global `cookieshieldData` injected via wp_localize_script.
 *
 * @package CookieShield
 */

( function () {
  'use strict';

  const DATA = window.cookieshieldData || {};
  const SETTINGS = DATA.settings || {};
  const COOKIE_NAME = 'cookieshield_consent';

  // ---------------------------------------------------------------------------
  // Consent module
  // ---------------------------------------------------------------------------

  function readConsent() {
    const cookies = document.cookie.split( ';' );
    for ( const cookie of cookies ) {
      const [ name, ...rest ] = cookie.trim().split( '=' );
      if ( name === COOKIE_NAME ) {
        try {
          return JSON.parse( decodeURIComponent( rest.join( '=' ) ) );
        } catch {
          return null;
        }
      }
    }
    return null;
  }

  function writeConsentCookie( payload ) {
    const lifetime = parseInt( SETTINGS.lifetime, 10 ) || 365;
    const expires = new Date();
    expires.setDate( expires.getDate() + lifetime );
    document.cookie = [
      COOKIE_NAME + '=' + encodeURIComponent( JSON.stringify( payload ) ),
      'expires=' + expires.toUTCString(),
      'path=/',
      'SameSite=Lax',
    ].join( '; ' );
  }

  function saveConsent( categories ) {
    const payload = {
      version: SETTINGS.version || '1.0',
      timestamp: new Date().toISOString(),
      categories: Object.assign( { necessary: true }, categories ),
    };

    writeConsentCookie( payload );

    // Fire-and-forget — do not block UI on server round-trip.
    fetch( DATA.restUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': DATA.nonce,
      },
      body: JSON.stringify( { categories } ),
    } ).catch( ( err ) => {
      console.warn( 'CookieShield: could not sync consent to server.', err );
    } );

    return payload;
  }

  // ---------------------------------------------------------------------------
  // DOM references
  // ---------------------------------------------------------------------------

  let banner, reopenBtn;

  // ---------------------------------------------------------------------------
  // Banner visibility
  // ---------------------------------------------------------------------------

  function showBanner() {
    if ( ! banner ) return;
    banner.removeAttribute( 'hidden' );
    requestAnimationFrame( () => banner.classList.add( 'cookieshield-banner--visible' ) );
    if ( reopenBtn ) reopenBtn.setAttribute( 'hidden', '' );
  }

  function hideBanner() {
    if ( ! banner ) return;
    banner.classList.remove( 'cookieshield-banner--visible' );
    banner.addEventListener(
      'transitionend',
      () => {
        banner.setAttribute( 'hidden', '' );
        if ( reopenBtn ) reopenBtn.removeAttribute( 'hidden' );
      },
      { once: true }
    );
  }

  // ---------------------------------------------------------------------------
  // Consent actions
  // ---------------------------------------------------------------------------

  function acceptAll() {
    const categories = { analytics: true, marketing: true, preferences: true };
    const payload = saveConsent( categories );
    hideBanner();
    unblockScripts( categories );
    dispatch( 'cookieshield:accept', { categories: payload.categories } );
  }

  function rejectAll() {
    const categories = { analytics: false, marketing: false, preferences: false };
    const payload = saveConsent( categories );
    hideBanner();
    dispatch( 'cookieshield:reject', { categories: payload.categories } );
  }

  function saveSelection() {
    const toggles = banner.querySelectorAll( '.cookieshield-toggle__input' );
    const categories = {};
    toggles.forEach( ( toggle ) => {
      categories[ toggle.name ] = toggle.checked;
    } );
    const payload = saveConsent( categories );
    hideBanner();
    unblockScripts( categories );
    dispatch( 'cookieshield:save', { categories: payload.categories } );
  }

  // ---------------------------------------------------------------------------
  // Script unblocking
  // ---------------------------------------------------------------------------

  function unblockScripts( categories ) {
    const blocked = document.querySelectorAll( 'script[type="text/plain"][data-cookieshield]' );
    blocked.forEach( ( el ) => {
      const cat = el.getAttribute( 'data-cookieshield' );
      if ( categories[ cat ] ) {
        const clone = document.createElement( 'script' );
        Array.from( el.attributes ).forEach( ( attr ) => {
          if ( attr.name === 'type' ) return;
          if ( attr.name === 'data-src' ) {
            clone.setAttribute( 'src', attr.value );
          } else {
            clone.setAttribute( attr.name, attr.value );
          }
        } );
        if ( ! el.getAttribute( 'data-src' ) && el.textContent ) {
          clone.textContent = el.textContent;
        }
        el.parentNode.replaceChild( clone, el );
      }
    } );
  }

  // ---------------------------------------------------------------------------
  // Tab switching
  // ---------------------------------------------------------------------------

  function initTabs() {
    const tabBtns   = banner.querySelectorAll( '.cookieshield-tab' );
    const panels    = banner.querySelectorAll( '.cookieshield-banner__panel' );

    tabBtns.forEach( ( btn ) => {
      btn.addEventListener( 'click', () => {
        const target = btn.dataset.cstab;

        tabBtns.forEach( ( b ) => {
          b.classList.remove( 'is-active' );
          b.setAttribute( 'aria-selected', 'false' );
        } );
        panels.forEach( ( p ) => {
          p.classList.remove( 'is-active' );
          p.setAttribute( 'hidden', '' );
        } );

        btn.classList.add( 'is-active' );
        btn.setAttribute( 'aria-selected', 'true' );
        const panel = banner.querySelector( `.cookieshield-banner__panel[data-cspanel="${target}"]` );
        if ( panel ) {
          panel.classList.add( 'is-active' );
          panel.removeAttribute( 'hidden' );
        }
      } );
    } );
  }

  // ---------------------------------------------------------------------------
  // Focus trap
  // ---------------------------------------------------------------------------

  function trapFocus( container ) {
    const focusable = container.querySelectorAll(
      'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    const first = focusable[ 0 ];
    const last  = focusable[ focusable.length - 1 ];
    if ( first ) first.focus();

    container.addEventListener( 'keydown', function handler( e ) {
      if ( e.key !== 'Tab' ) return;
      if ( e.shiftKey ) {
        if ( document.activeElement === first ) { e.preventDefault(); last.focus(); }
      } else {
        if ( document.activeElement === last ) { e.preventDefault(); first.focus(); }
      }
    } );
  }

  // ---------------------------------------------------------------------------
  // Custom event helper
  // ---------------------------------------------------------------------------

  function dispatch( name, detail ) {
    document.dispatchEvent( new CustomEvent( name, { detail, bubbles: true } ) );
  }

  // ---------------------------------------------------------------------------
  // Populate text and toggles from settings
  // ---------------------------------------------------------------------------

  function populateText() {
    const s = SETTINGS;

    // Tab labels.
    const tabConsent = document.getElementById( 'cs-tab-consent' );
    const tabInfo    = document.getElementById( 'cs-tab-info' );
    const tabAbout   = document.getElementById( 'cs-tab-about' );
    if ( tabConsent ) tabConsent.textContent = s.tabConsent || 'Consent';
    if ( tabInfo )    tabInfo.textContent    = s.tabInfo    || 'Information';
    if ( tabAbout )   tabAbout.textContent   = s.tabAbout   || 'About';

    // Banner title + description (consent panel).
    const titleEls = banner.querySelectorAll( '.cookieshield-banner__title' );
    const descEls  = banner.querySelectorAll( '.cookieshield-banner__panel[data-cspanel="consent"] .cookieshield-banner__description' );
    titleEls.forEach( ( el ) => { el.textContent = s.bannerTitle || ''; } );
    descEls.forEach( ( el )  => { el.textContent = s.bannerDesc  || ''; } );

    // Info panel description (reuse banner description).
    const infoDesc = banner.querySelector( '.cookieshield-banner__panel[data-cspanel="info"] .cookieshield-banner__description' );
    if ( infoDesc ) infoDesc.textContent = s.bannerDesc || '';

    // Privacy link.
    const privacyLinks = banner.querySelectorAll( '.cookieshield-privacy-link' );
    privacyLinks.forEach( ( el ) => {
      if ( s.privacyUrl ) {
        el.href = s.privacyUrl;
        el.removeAttribute( 'hidden' );
      } else {
        el.setAttribute( 'hidden', '' );
      }
    } );

    // Category labels.
    const necessaryLabel  = document.getElementById( 'cs-cat-necessary-label' );
    const prefLabel       = document.getElementById( 'cs-cat-pref-label' );
    const analyticsLabel  = document.getElementById( 'cs-cat-analytics-label' );
    const marketingLabel  = document.getElementById( 'cs-cat-marketing-label' );
    if ( necessaryLabel )  necessaryLabel.textContent  = s.necessaryLabel   || 'Necessary';
    if ( prefLabel )       prefLabel.textContent       = s.preferencesLabel || 'Preferences';
    if ( analyticsLabel )  analyticsLabel.textContent  = s.analyticsLabel   || 'Statistics';
    if ( marketingLabel )  marketingLabel.textContent  = s.marketingLabel   || 'Marketing';

    // Footer buttons.
    const acceptBtn    = document.getElementById( 'cs-accept-all' );
    const rejectBtn    = document.getElementById( 'cs-reject-all' );
    const selectionBtn = document.getElementById( 'cs-save-selection' );
    if ( acceptBtn )    acceptBtn.textContent    = s.acceptLabel    || 'Allow all';
    if ( selectionBtn ) selectionBtn.textContent = s.selectionLabel || 'Allow selection';
    if ( rejectBtn ) {
      rejectBtn.textContent = s.rejectLabel || 'Reject';
      if ( ! s.showReject ) rejectBtn.setAttribute( 'hidden', '' );
    }
  }

  function populateToggles( consent ) {
    [ 'analytics', 'marketing', 'preferences' ].forEach( ( cat ) => {
      const toggle = document.getElementById( `cs-toggle-${cat}` );
      if ( toggle ) {
        toggle.checked = consent ? !! consent.categories[ cat ] : false;
      }
    } );
  }

  // ---------------------------------------------------------------------------
  // Init
  // ---------------------------------------------------------------------------

  document.addEventListener( 'DOMContentLoaded', function () {
    banner    = document.getElementById( 'cookieshield-banner' );
    reopenBtn = document.getElementById( 'cookieshield-reopen' );

    if ( ! banner ) return;

    populateText();
    initTabs();

    const consent    = readConsent();
    const isPreview  = document.body.classList.contains( 'cookieshield-preview' );

    if ( consent && ! isPreview ) {
      if ( reopenBtn ) reopenBtn.removeAttribute( 'hidden' );
      dispatch( 'cookieshield:ready', { consent } );
    } else {
      showBanner();
      trapFocus( banner.querySelector( '.cookieshield-banner__dialog' ) || banner );
      dispatch( 'cookieshield:ready', { consent: null } );
    }

    populateToggles( consent );

    // Button listeners.
    const acceptAllBtn    = document.getElementById( 'cs-accept-all' );
    const rejectAllBtn    = document.getElementById( 'cs-reject-all' );
    const saveSelBtn      = document.getElementById( 'cs-save-selection' );
    const overlay         = banner.querySelector( '.cookieshield-banner__overlay' );

    if ( acceptAllBtn ) acceptAllBtn.addEventListener( 'click', acceptAll );
    if ( rejectAllBtn ) rejectAllBtn.addEventListener( 'click', rejectAll );
    if ( saveSelBtn )   saveSelBtn.addEventListener( 'click', saveSelection );
    if ( overlay )      overlay.addEventListener( 'click', hideBanner );

    if ( reopenBtn ) {
      reopenBtn.addEventListener( 'click', () => {
        populateToggles( readConsent() );
        showBanner();
      } );
    }

    document.addEventListener( 'keydown', ( e ) => {
      if ( e.key === 'Escape' && banner && ! banner.hasAttribute( 'hidden' ) ) {
        hideBanner();
      }
    } );
  } );

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  window.CookieConsent = {
    hasConsent( category ) {
      if ( category === 'necessary' ) return true;
      const consent = readConsent();
      return !! ( consent && consent.categories && consent.categories[ category ] );
    },
    getConsent() {
      return readConsent();
    },
    openBanner() {
      showBanner();
    },
    revokeConsent() {
      document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
      window.location.reload();
    },
  };

} )();
