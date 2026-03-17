# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**CookieShield** is a WordPress plugin built by a Swedish web agency for GDPR-compliant cookie consent management. It targets non-technical clients using WordPress, often with Elementor. The goal is a lightweight, conflict-free plugin with a professional look comparable to Cookiebot/Usercentrics.

## Tech Stack

- **PHP** — WordPress coding standards (WPCS). No external PHP dependencies beyond WordPress core.
- **Vanilla JavaScript** — No jQuery. ES6+ with no build step; scripts are enqueued via WordPress.
- **CSS** — Custom properties (variables) for theming. No preprocessors.

## Architecture

The plugin follows standard WordPress plugin structure:

```
cookieshield/
├── cookieshield.php          # Main plugin file: metadata, bootstrap, activation/deactivation hooks
├── includes/                 # PHP classes and logic
│   ├── class-cookieshield.php        # Core plugin class, hooks registration
│   ├── class-consent-manager.php     # Consent storage and retrieval logic
│   ├── class-script-blocker.php      # Blocks scripts by category until consent
│   ├── class-admin-settings.php      # WordPress settings page (WP Settings API)
│   └── class-rest-api.php            # REST endpoint for saving consent
├── admin/                    # Admin-side assets
│   ├── css/admin.css
│   └── js/admin.js
├── public/                   # Front-end assets
│   ├── css/banner.css        # Banner styles using CSS custom properties
│   └── js/banner.js          # Banner behavior, consent logic, cookie read/write
├── languages/                # .pot file and translations (text domain: cookieshield)
└── uninstall.php             # Cleanup on plugin deletion
```

**Data flow:**
1. PHP checks for existing consent cookie on page load; if absent, the banner is rendered.
2. `banner.js` handles user interaction and POSTs consent choices to the REST API.
3. The REST endpoint validates and stores consent in a cookie (`cookieshield_consent`).
4. `script-blocker.php` uses `script_loader_tag` filter to defer non-essential scripts until consent is confirmed client-side via a `CustomEvent`.

## WordPress Conventions

- Use the **WP Settings API** for all admin options (no custom option tables).
- Prefix all functions, hooks, options, and globals with `cookieshield_` or use the `CookieShield_` class prefix.
- Sanitize inputs with WordPress sanitization functions (`sanitize_text_field`, `absint`, etc.); escape all outputs (`esc_html`, `esc_attr`, `wp_kses`).
- Enqueue scripts/styles with `wp_enqueue_scripts` and `admin_enqueue_scripts`. Always pass a version parameter.
- Use `wp_localize_script` to pass PHP config (REST nonce, settings) to JavaScript.
- REST routes registered under namespace `cookieshield/v1`.

## JavaScript Conventions

- No jQuery, no build tools. Plain ES6+ loaded as a WordPress-enqueued script.
- Consent state is read/written from a first-party cookie (`cookieshield_consent`) as a JSON string.
- Use `CustomEvent` to signal consent categories to other scripts (e.g., `cookieshield:consent`).
- Keep `banner.js` self-contained; it must not rely on any global except `cookieshieldData` (injected via `wp_localize_script`).

## CSS / Theming

- All visual values (colors, border-radius, font sizes, z-index) are CSS custom properties defined on `:root` or a scoped `.cookieshield-*` selector.
- Admin settings expose a color picker that writes to these custom properties inline, so clients can theme the banner without touching CSS.
- Class names follow BEM: `.cookieshield-banner`, `.cookieshield-banner__actions`, `.cookieshield-banner--hidden`.

## GDPR Requirements

- Consent must be **opt-in** (no pre-ticked boxes).
- Non-essential scripts (analytics, marketing, preferences) must not run before consent.
- Users must be able to withdraw consent as easily as they gave it (floating re-open button).
- Consent records should store: timestamp, version of the consent notice, and categories accepted.
- Essential/functional cookies are always active and not presented as a choice.

## Code Style

- Comments are always written in **English**.
- PHP: 4-space indentation, Yoda conditions, `/** */` docblocks on all methods.
- JS: 2-space indentation, `const`/`let` only, descriptive variable names.
- CSS: 2-space indentation, mobile-first media queries.
