Refactor the Industriesalon theme CSS color system.

Goal:
Add a semantic accent-color layer so pages/sections can switch from the default red scheme to blue/green/yellow/brown without rewriting component selectors.

Files to edit:
- themes/industriesalon/style.css
- themes/industriesalon/cards.css
- themes/industriesalon/patterns.css
Do not edit theme.json unless absolutely necessary.

Rules:
1. Keep the brand palette variables unchanged:
   --iss-red, --iss-white, --iss-black, --iss-grey, --iss-green, --iss-blue, --iss-yellow, --iss-brown

2. In style.css, inside :root, add semantic variables:
   --iss-accent: var(--iss-red);
   --iss-accent-rgb: 232, 29, 37;
   --iss-accent-soft: rgba(var(--iss-accent-rgb), 0.16);
   --iss-accent-border: rgba(var(--iss-accent-rgb), 0.28);

3. Add scheme classes in style.css:
   .iss-scheme-red
   .iss-scheme-blue
   .iss-scheme-green
   .iss-scheme-yellow
   .iss-scheme-brown

Each class should override only:
   --iss-accent
   --iss-accent-rgb
   --iss-accent-soft
   --iss-accent-border

Use:
   red: 232, 29, 37
   blue: 134, 159, 186
   green: 87, 158, 125
   yellow: 235, 188, 30
   brown: 117, 58, 24

4. Replace generic accent usages from var(--iss-red) to var(--iss-accent).
Only replace usages where red functions as a UI accent:
   - rails
   - kicker border/dot
   - separators
   - hover colors
   - standard buttons
   - card rails
   - icon accents
   - note markers
   - timeline primary color
   - plugin bridge primary accents

5. Do NOT replace var(--iss-red) where red is explicitly part of a named modifier:
   - .iss-card--red
   - .iss-media-card--red
   - .iss-kicker--red if it exists
   - .iss-timeline--scheme-scarlet-red
   - any class whose name explicitly says red/scarlet
   These must stay fixed red.

6. Keep component-specific color modifiers intact:
   .iss-card--blue should still force blue
   .iss-card--green should still force green
   .iss-media-card--blue should still force blue
   etc.

7. Where rgba(232, 29, 37, X) is used for generic accents, replace with rgba(var(--iss-accent-rgb), X).
Do not replace it inside scarlet-red/red-specific scheme classes.

8. Update local component variables where useful:
   .iss-media-card should default:
   --iss-media-card-rail-color: var(--iss-accent);

   timeline default should use:
   --iss-tl-primary: var(--iss-accent);
   --iss-tl-line: var(--iss-accent-border);

9. Do not change layout, spacing, selectors, file organization, comments, or markup.
Only color-token refactor.

10. After changes, search all three CSS files for:
   var(--iss-red)
   rgba(232, 29, 37
Confirm remaining instances are either:
   - explicit red/scarlet modifiers
   - legacy aliases
   - intentional brand-specific red usage
   - dark hero note exceptions where no scheme switch is desired

11. Return a short summary and the list of changed files. Do not paste full files unless I ask.