Replace timeline rendering with card-based list mode

Not remove timeline → change its renderer

Same data → different output

Instead of:

| date
| title
| text
| button

Render:

[ card ] [ card ] [ card ]
Target behavior
Default view = cards (not timeline)
grid (2–3 columns desktop)
each item = compact card
sorted by next date
Optional toggle

Keep your idea:

Liste (cards) | Kalender

Timeline can exist internally, but:
👉 not the default visual

5. Concrete UI problems in current booking section
A. Double heading layer
Nächste Termine
+ explanation paragraph
+ kicker "Liste"

Too much framing for a functional block.

👉 Fix:

remove “Liste” kicker
shorten intro text to 1 line
B. Switches
Liste | Gruppen anfragen

Problem:

asymmetric actions (view vs action)
visually weak

👉 Fix:

[ Liste ] [ Kalender ]    (view)
-----------------------
[ Gruppen anfragen ]      (secondary CTA)
C. Timeline styling (red scheme)

Too strong:

iss-timeline--scheme-scarlet-red

From brand guide:

red = accent, not structure

👉 Fix:

use grey/black as base
red only for buttons / highlights
6. Cards vs timeline (your intuition)

You said:

timeline should be rendered differently as cards

Correct. More precise:

timeline is a data model, not a UI model