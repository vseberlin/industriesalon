# Global Theme & Pattern Audit: Refactoring for Stability

## Executive Summary
The `industriesalon` theme is suffering from **architectural drift**. High-frequency patterns (Heroes, Card Rows, Intro Sections) are implemented with slight variations across templates, leading to fragile page-specific CSS overrides and broken Gutenberg editor experiences.

## Core Findings

### 1. Structural Inconsistency
*   **The Hero Crisis:** Three different implementations of "Hero" exist (`front-page.html`, `hero-page.html`, and patterns like `archive-landing.html`).
    *   *Issue:* Some use `tagName: "section"`, others generic `div`. Vertical height and CTA positioning rely on different CSS files.
*   **Container Conflict:** The theme uses a manual `.iss-container` for caging, but many patterns still have `layout: {"type":"constrained"}` active. 
    *   *Editor Impact:* Gutenberg injects `.is-layout-constrained` which adds internal padding and margin logic that "fights" the theme's `.iss-container`, causing broken layouts in the editor.

### 2. CSS Fragility
*   **Override Overload:** `front-page.css` and page-specific files (e.g., `page-ausstellungen.css`) contain layout logic that should be global.
*   **Logic Leakage:** Responsive grid definitions are repeated across files instead of using global utility classes or tokens.

### 3. Gutenberg Validity Gaps
*   **TagName Mismatch:** Patterns often lack the `tagName: "section"` attribute, breaking semantic consistency when compared to standardized templates.
*   **Hardcoded Assets:** Numerous images and links still point to local dev IPs (`192.168.2.31`), which invalidates blocks when the environment changes.

---

## Global Refactoring Proposal

### Strategy A: The "Section Contract" (Standardization)
Promote the `iss-section > iss-container` hierarchy to a **Global Core Pattern**.
*   **Action:** Remove `layout: {"type":"constrained"}` from all patterns and templates.
*   **Action:** Ensure every section uses `tagName: "section"`.
*   **Globalized CSS:** Move vertical rhythm (padding) and width control to `style.css` base classes.

### Strategy B: Hero Normalization
Merge all variations into a single **"ISS Front Hero" Component**.
*   **Unified Height:** Use a global `--iss-hero-height` token (e.g., `80vh`).
*   **Synced Floor:** Lock the CTA positioning relative to the content floor inside the container, not the viewport.

### Strategy C: Token-Driven Patterns
Move pattern-specific design logic into `theme.json` where possible, and use `patterns.css` only for structural layout.
*   **Action:** Promote `.iss-kicker`, `.iss-heading`, and `.iss-media-card` to **Global Design Tokens**.

---

## Implementation Roadmap
1.  **Phase 1: Token Cleanse (style.css)** - Consolidate section/container/hero tokens.
2.  **Phase 2: Markup Normalization** - Batch update all patterns to use the standard Section Contract.
3.  **Phase 3: Editor Calibration** - Fix the Gutenberg alignment by removing conflicting constrained layouts.
4.  **Phase 4: Global Pattern Promotion** - Extract logic from `front-page.css` and page-specific files into `patterns.css`.

## Gutenberg Editor Health Report
| Status | Issue | Fix |
| :--- | :--- | :--- |
| ⚠️ Warning | Layout Fighting | Disable `constrained` layout on Group blocks. |
| ❌ Error | Nested Paragraphs | Fix microblock icons (already started on front-page). |
| ⚠️ Warning | Relative URLs | Batch replace dev IPs with relative paths. |
| ⚠️ Warning | CTA Jumping | Move CTA logic to global Hero contract. |
