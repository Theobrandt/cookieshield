/**
 * CookieShield Admin — settings page interactions.
 *
 * @package CookieShield
 */

( function () {
  'use strict';

  // --------------------------------------------------------------------------
  // Tab switching
  // --------------------------------------------------------------------------

  const tabBtns   = document.querySelectorAll( '.cs-tabs__btn' );
  const tabPanels = document.querySelectorAll( '.cs-tabs__panel' );

  tabBtns.forEach( ( btn ) => {
    btn.addEventListener( 'click', () => {
      const target = btn.dataset.tab;

      tabBtns.forEach( ( b ) => b.classList.remove( 'is-active' ) );
      tabPanels.forEach( ( p ) => p.classList.remove( 'is-active' ) );

      btn.classList.add( 'is-active' );
      const panel = document.querySelector( `.cs-tabs__panel[data-panel="${target}"]` );
      if ( panel ) panel.classList.add( 'is-active' );
    } );
  } );

  // --------------------------------------------------------------------------
  // Color picker live preview
  // --------------------------------------------------------------------------

  const iframe = document.getElementById( 'cs-preview-iframe' );

  const COLOR_VAR_MAP = {
    color_bg:          '--cs-bg',
    color_text:        '--cs-text',
    color_primary:     '--cs-primary',
    color_primary_text:'--cs-primary-text',
  };

  /**
   * Sends a CSS variable update into the preview iframe.
   *
   * @param {string} varName
   * @param {string} value
   */
  function updateIframeVar( varName, value ) {
    if ( ! iframe || ! iframe.contentDocument ) return;
    try {
      const iDoc = iframe.contentDocument;
      const styleTag = iDoc.getElementById( 'cookieshield-theme' );
      if ( ! styleTag ) return;

      let css = styleTag.textContent;
      const regex = new RegExp( varName + '\\s*:[^;]+;' );
      css = css.replace( regex, varName + ':' + value + ';' );
      styleTag.textContent = css;
    } catch {
      // Cross-origin iframe — silently ignore.
    }
  }

  document.querySelectorAll( '.cs-color-picker' ).forEach( ( picker ) => {
    const fieldId  = picker.dataset.csField;
    const cssVar   = COLOR_VAR_MAP[ fieldId ];
    const swatch   = picker.nextElementSibling;

    picker.addEventListener( 'input', () => {
      const val = picker.value;
      if ( swatch ) swatch.style.background = val;
      if ( cssVar ) updateIframeVar( cssVar, val );
    } );
  } );

  // --------------------------------------------------------------------------
  // Position selector live preview
  // --------------------------------------------------------------------------

  const positionSelect = document.querySelector( 'select[data-cs-field="banner_position"]' );

  const POSITION_CLASSES = [
    'cookieshield-banner--bottom-bar',
    'cookieshield-banner--bottom-left',
    'cookieshield-banner--bottom-right',
    'cookieshield-banner--center-modal',
  ];

  if ( positionSelect ) {
    positionSelect.addEventListener( 'change', () => {
      const newPosition = positionSelect.value;
      if ( ! iframe || ! iframe.contentDocument ) return;
      try {
        const banner = iframe.contentDocument.getElementById( 'cookieshield-banner' );
        if ( ! banner ) return;
        POSITION_CLASSES.forEach( ( cls ) => banner.classList.remove( cls ) );
        banner.classList.add( 'cookieshield-banner--' + newPosition );
      } catch {
        // Cross-origin — ignore.
      }
    } );
  }

  // --------------------------------------------------------------------------
  // Language selector — auto-fills text fields when language changes
  // --------------------------------------------------------------------------

  const langSelect = document.querySelector( 'select[data-cs-field="banner_language"]' );
  const LANG_FIELD_MAP = {
    banner_title:            'banner_title',
    banner_description:      'banner_description',
    accept_label:            'accept_label',
    reject_label:            'reject_label',
    settings_label:          'settings_label',
    save_label:              'save_label',
    analytics_label:         'analytics_label',
    analytics_description:   'analytics_description',
    marketing_label:         'marketing_label',
    marketing_description:   'marketing_description',
    preferences_label:       'preferences_label',
    preferences_description: 'preferences_description',
  };

  if ( langSelect && window.cookieshieldAdmin ) {
    langSelect.addEventListener( 'change', () => {
      const lang = langSelect.value;
      if ( lang === 'auto' ) return; // 'auto' keeps whatever the user typed.

      const strings = window.cookieshieldAdmin.languages[ lang ];
      if ( ! strings ) return;

      Object.entries( LANG_FIELD_MAP ).forEach( ( [ stringKey, fieldId ] ) => {
        const el = document.getElementById( fieldId );
        if ( el && strings[ stringKey ] !== undefined ) {
          el.value = strings[ stringKey ];
        }
      } );
    } );
  }

  // --------------------------------------------------------------------------
  // Reload iframe when saved
  // --------------------------------------------------------------------------

  const form = document.querySelector( '.cookieshield-admin__form form' );
  if ( form ) {
    form.addEventListener( 'submit', () => {
      // Reload iframe after a short delay to pick up new settings.
      setTimeout( () => {
        if ( iframe ) iframe.contentWindow.location.reload();
      }, 800 );
    } );
  }

} )();
