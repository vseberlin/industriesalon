<?php
/**
 * Title: Highlight – Teilchendetektor
 * Slug: industriesalon/highlight-teilchendetektor
 * Categories: industriesalon, featured
 * Description: Dark front-page highlight section for the WF silicon particle detector.
 */
?>

<!-- wp:group {"tagName":"section","className":"iss-section iss-section--dark iss-highlight-section","layout":{"type":"constrained"}} -->
<section class="wp-block-group iss-section iss-section--dark iss-highlight-section">

  <!-- wp:group {"className":"iss-container iss-highlight","layout":{"type":"default"}} -->
  <div class="wp-block-group iss-container iss-highlight">

    <!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"iss-highlight__media"} -->
    <figure class="wp-block-image size-large iss-highlight__media">
      <img src="/wp-content/uploads/teilchendetektor-clean.jpg" alt="Silizium-Teilchendetektor aus dem Werk für Fernsehelektronik, 1990"/>
    </figure>
    <!-- /wp:image -->

    <!-- wp:group {"className":"iss-highlight__content","layout":{"type":"default"}} -->
    <div class="wp-block-group iss-highlight__content">

      <!-- wp:paragraph {"className":"iss-kicker iss-kicker--light"} -->
      <p class="iss-kicker iss-kicker--light">Forschung</p>
      <!-- /wp:paragraph -->

      <!-- wp:heading {"level":2,"className":"iss-heading iss-heading--light"} -->
      <h2 class="wp-block-heading iss-heading iss-heading--light">Teilchendetektor aus Schöneweide</h2>
      <!-- /wp:heading -->

      <!-- wp:paragraph {"className":"iss-text iss-text--light"} -->
      <p class="iss-text iss-text--light">Ein Prototyp aus der Forschungsabteilung des Werkes für Fernsehelektronik: Der freiliegende Siliziumchip registrierte ionisierende Teilchen direkt im Halbleitermaterial. Das Objekt zeigt eine Seite des WF, die im Schatten der Bildröhrenproduktion oft unsichtbar blieb – hochpräzise Sensorik, Messtechnik und experimentelle Halbleiterentwicklung.</p>
      <!-- /wp:paragraph -->

      <!-- wp:buttons {"className":"iss-buttons"} -->
      <div class="wp-block-buttons iss-buttons">
        <!-- wp:button {"className":"iss-button iss-button--light"} -->
        <div class="wp-block-button iss-button iss-button--light">
          <a class="wp-block-button__link wp-element-button" href="/mediathek/teilchendetektor/">Mehr erfahren</a>
        </div>
        <!-- /wp:button -->
      </div>
      <!-- /wp:buttons -->

    </div>
    <!-- /wp:group -->

  </div>
  <!-- /wp:group -->

</section>
<!-- /wp:group -->



/* ------------------------------------------------------------
   Highlight: Teilchendetektor / dark research feature
------------------------------------------------------------ */

.iss-highlight-section {
  background:
    radial-gradient(circle at 20% 30%, rgba(180, 70, 45, 0.14), transparent 28%),
    radial-gradient(circle at 80% 65%, rgba(255, 255, 255, 0.06), transparent 26%),
    #0e0e0e;
  color: #e8e8e8;
  padding: clamp(64px, 8vw, 128px) 0;
}

.iss-highlight {
  display: grid;
  grid-template-columns: minmax(0, 1.12fr) minmax(360px, 0.88fr);
  gap: clamp(40px, 6vw, 104px);
  align-items: center;
}

.iss-highlight__media {
  margin: 0;
}

.iss-highlight__media img {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 8px;
  background: #171717;
  box-shadow: 0 28px 80px rgba(0, 0, 0, 0.55);
}

.iss-highlight__content {
  max-width: 620px;
}

.iss-highlight__content .iss-kicker {
  margin-bottom: 16px;
}

.iss-highlight__content .iss-heading {
  margin-bottom: 22px;
  color: #fff;
}

.iss-highlight__content .iss-text {
  color: rgba(255, 255, 255, 0.72);
  max-width: 580px;
}

.iss-highlight-section .iss-button .wp-block-button__link {
  background: transparent;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.42);
  border-radius: 999px;
  padding: 0.75em 1.25em;
}

.iss-highlight-section .iss-button .wp-block-button__link:hover {
  border-color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

@media (max-width: 900px) {
  .iss-highlight {
    grid-template-columns: 1fr;
  }

  .iss-highlight__content {
    max-width: 680px;
  }
}


dark background → separates from your zebra sections
image left, text right → stable reading pattern
no rails, no heavy accents → keeps it “research”, not marketing
tight text width → avoids the “white desert” problem
soft shadow only → object feels present, not dramatic

Yes, but not as a “hero attraction.” It works better as a focused highlight module inside a narrative, otherwise it will feel too technical and isolated.

The object has strong qualities (authenticity, story, visual texture), but it needs framing to become legible to non-specialists.

Where it fits best on your site

1. About / Über uns (very good placement)
Use it as proof that the Industriesalon deals with real engineering depth, not only storytelling.

Position:
→ after mission / before projects

Framing:

“Forschung und Entwicklung in Schöneweide – oft unsichtbar, aber zentral”

2. Mediathek / Sammlung (ideal long-term home)
Here it can live as a detailed object page with:

images (front/back/macro)
the note
long explanation (the text we wrote)

3. Front page (only as a controlled teaser)
Not full-width, not dominant.

Use as:
→ one card in a 1–3 grid

Title example:

„Ein Prototyp aus der Forschung“

Text:

Silizium-Teilchendetektor aus dem WF, 1990

CTA:
→ „Mehr erfahren“

How to present it visually (important)

Do not treat it like a product.

Better approach:

dark neutral background
object slightly angled or cropped (not perfectly centered)
include macro detail alongside the full object

This matches your “industrial + calm” tone.

Content structure (fits your system)

Use your kicker / heading / text trio:

Kicker:
Forschung

Heading:
Teilchendetektor aus Schöneweide

Text:
Kurz erklären: Prototyp, offener Chip, Messung von Teilchen

What to avoid
no oversized headline like “teuerster Chip”
no full hero section → too niche, breaks flow
no purely technical explanation without narrative
What makes it a good highlight

Not the object itself, but what it represents:

hidden R&D in DDR industry
transition from Röhren → Halbleiter → Sensorik
individuals like Schimko (human layer)

That’s your real content.