# Plugin Audit: Architectural Fragility & Theme Integration

## Executive Summary
The custom plugins in the `industriesalon` ecosystem (especially `iss-fuehrungen`, `industriesalon-notices`, and `saas-api`) provide essential business logic but suffer from **"Theme Shadowing"**. They frequently reimplement theme-owned layout patterns (`iss-container`, `iss-heading`, `iss-kicker`) in PHP, creating a secondary source of truth that breaks when the theme's CSS or markup contract changes.

## Core Findings

### 1. Style & Markup Duplication ("Shadowing")
*   **The Problem:** Plugins like `iss-fuehrungen` and `industriesalon-notices` have hardcoded HTML strings in their PHP render callbacks that mimic the theme's BEM classes.
*   **The Risk:** If the theme changes `.iss-kicker` to use a different wrapper or class name, all plugin-generated kickers will look "broken" or inconsistent.
*   **Example:** `render_front_notice()` in the notices plugin manually constructs an `<aside class="iss-hero-note">`.

### 2. Runtime & Stability Risks
*   **Hardcoded Environmentals:** Like the theme, some plugins still contain hardcoded dev URLs (`192.168.2.31`), which will cause "Mixed Content" warnings or broken images in production.
*   **SuperSaaS Dependency:** `iss-fuehrungen` and `saas-api` are tightly coupled. A failure in the external API could potentially hang the frontend if not handled with robust timeouts or stale-cache fallbacks.
*   **Template Logic Bloat:** `single-fuehrung.php` in the plugin uses `do_blocks()` extensively. While clever, this makes the template difficult to edit for non-technical users as the "Layout" is split between a PHP file and the Block Editor.

### 3. Usability for Non-Tech Users
*   **Meta Box vs. Blocks:** Many plugins still rely on traditional Meta Boxes (via `add_meta_boxes`) for critical data (e.g., Notice Banner area/skin).
    *   *User Impact:* The user has to scroll past the Block Editor to a separate interface to control the appearance, breaking the "WYSIWYG" flow.
*   **Lack of Previews:** Complex dynamic blocks (like `timeline-query`) likely appear as "Loading..." or empty placeholders in the editor, making page building a "guess and check" process.

---

## Technical Recommendations

### Strategy A: The "Design System" Bridge
*   **Action:** Move common markup generators (e.g., `render_kicker`, `render_heading`) into a Shared Library or a Theme-Level helper that plugins can call.
*   **Benefit:** Change a class name once; it updates site-wide across all plugins.

### Strategy B: Asset Hygiene
*   **Action:** Centralize CSS for "Hero Notes" and "Cards" in the theme. Plugins should ONLY provide the raw data; the theme should own the visual "Skin."
*   **Action:** Batch replace all dev IPs with `WP_CONTENT_URL` or relative paths.

### Strategy C: Admin Experience
*   **Action:** Convert Meta Boxes to **Block Attributes** where possible.
*   **Action:** Use `edit.js` in blocks to provide a "Skeleton Screen" or real preview in the Gutenberg editor.

---

## Specific Plugin Health Report

| Plugin | Health | Major Issue | Recommendation |
| :--- | :--- | :--- | :--- |
| `iss-fuehrungen` | ⚠️ Caution | Hardcoded Template Markup | Move `single-fuehrung.php` logic to a Block Pattern. |
| `industriesalon-notices`| ✅ Good | Metadata split from content | Integrate banner settings into the Block Sidebar. |
| `saas-api` | ⚠️ Caution | External dependency fragility | Implement a more aggressive caching/stale-while-revalidate layer. |
| `iss-content-model` | ✅ Good | Standard CPT registration | Ensure all CPTs have `show_in_rest => true`. |
