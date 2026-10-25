# KI-Vorlagen: Vorschläge aus dem Bestand entwickeln und prüfen

Zwei Kopiervorlagen für ChatGPT, Claude, Gemini, Cursor oder andere KI-Werkzeuge.
Beide sorgen dafür, dass die KI **zuerst liest, was es schon gibt**, statt isoliert
zu entwerfen oder neu zu bauen.

- **Vorlage A – Entwurf:** Wer mit KI ein Konzept oder Design erstellt, beginnt hier.
- **Vorlage B – Prüfung:** Ein fertiger Vorschlag wird vor der Vorstandsbesprechung
  gegen den Bestand geprüft. Das Ergebnis gehört zu jedem Vorschlag dazu.

## Vorlage A – Entwurf aus dem Bestand

Den eigenen Wunsch unter **AUFGABE** einsetzen.

```text
Du entwirfst ein Konzept oder Design für die Website des Industriesalon
Schöneweide e.V. Die Website existiert bereits und hat ein festes Repertoire.

Lies zuerst vollständig:
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/vorstand/bestand.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/architecture/plugin-map.md
Wenn du diese Dateien nicht öffnen kannst, sage das und höre auf. Entwirf nichts
ohne sie.

AUFGABE:
<<< hier beschreiben, was erreicht werden soll – für wen, mit welchem Inhalt >>>

Arbeitsweise:
1. Wähle den passenden Inhaltstyp und die Gestaltungsvariante aus bestand.md.
2. Baue die Seite aus vorhandenen Bausteinen und Darstellungen. Nenne für jeden
   Abschnitt Baustein und Darstellung beim Namen.
3. Verwende nur Farben, Schriften und Größenstufen aus Kapitel 6.
4. Wenn etwas wirklich fehlt, beschreibe es am Ende in einer eigenen Liste
   „Fehlt im Bestand", mit Begründung, warum keine vorhandene Darstellung reicht.
5. Baue keine eigene Website, App oder Datenhaltung. Inhalte (Termine, Objekte,
   Orte, Personen) kommen aus der bestehenden Plattform.
6. Liefere am Ende: Seitenaufbau als Liste (Baustein → Darstellung → Inhalt),
   gewählte Variante, Liste „Fehlt im Bestand". Ein Mockup ist optional und muss
   denselben Aufbau zeigen.

Schreibe auf Deutsch.
```

---

## Vorlage B – Prüfung gegen den Bestand

Diese Vorlage sorgt dafür, dass die KI **zuerst den vorhandenen Code liest** und
einen Vorschlag daran misst, statt ihn isoliert zu bewerten oder neu zu bauen.

### Warum eine feste Vorlage?

KI-Antworten hängen stark von der Frage ab. „Wie machen wir unsere Website
einfacher und moderner?" setzt voraus, dass sie zu kompliziert und veraltet ist,
und die KI wird diese Annahme meist bestätigen. Eine neutrale, immer gleiche Frage
liefert vergleichbare Ergebnisse für alle Vorstandsmitglieder und schützt alle
Beteiligten vor Bestätigungsfehlern, auch die technische Leitung.

### So wird sie benutzt

1. Ein KI-Werkzeug mit Zugriff auf GitHub oder Webseiten öffnen. Ein reiner Chat
   ohne Lesezugriff reicht nicht; dann gilt Punkt 0 der Vorlage.
2. Den Block unten vollständig kopieren.
3. Unter **VORSCHLAG** den eigenen Vorschlag einsetzen (Text, Skizze, Link zu einer
   Mini-Website oder einem anderen Repository). Für eine reine Bestandsaufnahme ohne
   Vorschlag das Feld leer lassen.
4. Die Antwort unverändert an den Vorstand und die technische Leitung weitergeben,
   nicht nur eine Zusammenfassung.

---

```text
Rolle: Du bist ein unabhängiger technischer Gutachter. Du bist weder Anwalt des
Vorschlags noch des bestehenden Systems. Ziel ist eine belastbare
Entscheidungsgrundlage für den ehrenamtlichen Vorstand eines kleinen Vereins.

Gegenstand: Die Website und digitale Sammlung des Industriesalon Schöneweide e.V.
Repository: https://github.com/vseberlin/industriesalon (Branch main)

0. LESEPFLICHT
Lies, bevor du urteilst, mindestens diese Dateien vollständig, die erste zuerst:
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/vorstand/bestand.md
  (Bestandskatalog: alle Inhaltstypen, Bausteine, Darstellungen,
  Gestaltungsvarianten, Funktionen, Vorlagen und Design-Tokens)
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/AGENTS.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/README.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/architecture/plugin-map.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/agent/no-parallel-systems.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/architecture/source-of-truth.md
- https://raw.githubusercontent.com/vseberlin/industriesalon/main/docs/architecture/vanilla-fallback-mode-adr.md
Lies danach die Dateien, die der Vorschlag direkt berührt (Plugins unter
plugins/iss-*, Theme unter themes/industriesalon).
Wenn du eine dieser Dateien nicht öffnen kannst, sage das zu Beginn ausdrücklich
und liste auf, was du gelesen hast. Urteile nicht auf Grundlage von Dateinamen,
Ordnerstruktur oder Dateigrößen allein.
Hinweis: plugins/media-library-assistant, newsletter, query-monitor,
webp-converter-for-media und classic-editor-and-classic-widgets sind
Fremd-Plugins, nicht Eigenentwicklung. Rechne sie nicht in die Größe des
eigenen Codes ein.

1. BESTAND (kurz)
Was leistet das System heute? Welche Teile (Plugins, Theme, Datenbanktabellen)
sind für was zuständig? Benenne Stärken und Risiken gleichermaßen, jeweils mit
Dateipfad als Beleg.

2. VORSCHLAG
<<< hier den Vorschlag einsetzen, oder „keiner – nur Bestandsaufnahme" >>>

3. ABGLEICH (nur wenn ein Vorschlag vorliegt)
Zuerst die ZUORDNUNGSTABELLE. Zerlege den Vorschlag in einzelne Elemente
(Seiten, Abschnitte, Funktionen, Gestaltungsmerkmale) und ordne jedes einem
Eintrag aus bestand.md zu:

| Element des Vorschlags | Entspricht im Bestand (Kapitel, Eintrag) | Status |

Status ist genau einer von:
- VORHANDEN – direkt nutzbar, nur redaktionell
- KONFIGURIERBAR – vorhanden, braucht andere Darstellung, Variante oder Einstellung
- ERWEITERUNG – fehlt, gehört aber in einen vorhandenen Baustein oder
  Inhaltstyp (zuständigen Teil nennen)
- NEU – lässt sich nichts im Bestand zuordnen (begründen)

Zähle am Ende, wie viele Elemente in welchem Status sind.

Dann:
a) Welche Teile des Vorschlags kann das bestehende System bereits ganz oder
   teilweise? Nenne den zuständigen Teil laut plugin-map.md.
b) Was davon würde ein paralleles System erzeugen (doppelte Daten, doppelte
   Darstellung, zweite Quelle der Wahrheit)? Prüfe gegen no-parallel-systems.md.
c) Was ist tatsächlich neu, und wo gehört es laut Zuständigkeitskarte hin?
d) Welche Daten (Sammlungsobjekte, Veranstaltungen, Orte, Buchungen) würden
   dupliziert, abgekoppelt oder müssten migriert werden?
e) Aufwand und Folgekosten: Wer pflegt das in drei Jahren? Was passiert, wenn die
   Person, die es gebaut hat, nicht mehr verfügbar ist?

4. URTEIL
Gib eine der folgenden Empfehlungen und begründe sie in höchstens fünf Sätzen:
- Im Bestand umsetzen (mit Angabe des zuständigen Teils)
- Teilweise im Bestand umsetzen, Rest als klar abgegrenzte Erweiterung
- Als eigenständiges Projekt sinnvoll (nur mit Begründung, warum der Bestand es
  nicht leisten kann, und mit Regeln für den Datenaustausch)
- Nicht empfehlenswert

5. REGELN FÜR DESIGNVORSCHLÄGE
Wenn der Vorschlag ein Design, Mockup oder Layout enthält oder du selbst eines
vorschlägst:
- Verwende nur die Farben, Schriften und Größenstufen aus bestand.md Kapitel 6.
  Jede andere Farbe oder Schrift einzeln begründen.
- Benenne für jeden Bereich des Designs den Baustein und die Darstellung aus
  Kapitel 2 und die Gestaltungsvariante aus Kapitel 3.
- Wenn das Design wie eine vorhandene Variante aussieht, sage das, statt es als
  neu darzustellen.
- Erfinde keine Inhalte, Termine oder Objekte, die es nicht gibt. Platzhalter
  als solche kennzeichnen.

6. REGELN FÜR DEINE ANTWORT
- Jede Behauptung über den Code braucht einen Dateipfad. Was du nicht geprüft
  hast, kennzeichne als Vermutung.
- Schlage keinen Neubau, Framework-Wechsel oder Plattformwechsel vor, solange du
  nicht konkret belegt hast, welches Problem der Bestand nicht lösen kann.
- Bewerte nicht nach Geschmack oder Mode, sondern nach Wartbarkeit,
  Datenintegrität, Kosten und Ausfallsicherheit für einen kleinen Verein.
- Schreibe auf Deutsch, verständlich für Nicht-Programmierer. Fachbegriffe kurz
  erklären.
- Schreibe am Ende drei offene Fragen auf, die der Vorstand mit der technischen
  Leitung klären sollte.
```

---

## Was diese Vorlagen nicht sind

Sie ersetzen kein Gespräch mit der technischen Leitung. Sie soll das Gespräch auf
eine gemeinsame Grundlage stellen: Was gibt es schon, was fehlt wirklich, und was
kostet es dauerhaft.
