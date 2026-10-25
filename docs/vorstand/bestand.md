# Bestandskatalog: Was die Plattform heute schon kann

Dieses Dokument listet, was auf der Website des Industriesalon Schöneweide bereits
gebaut ist: Inhaltstypen, Bausteine, Gestaltungsvarianten, Funktionen und das
Designsystem. Es ist die Grundlage für jeden neuen Konzept- oder Designvorschlag.

**Grundregel:** Ein Vorschlag beginnt mit diesem Katalog. Erst wenn etwas hier nicht
vorkommt und sich auch nicht aus den vorhandenen Teilen zusammensetzen lässt, ist es
wirklich neu.

**Für KI-Werkzeuge:** Ordne jedes Element eines Vorschlags einem Eintrag dieses
Katalogs zu (Inhaltstyp, Baustein, Darstellung, Gestaltungsvariante, Funktion,
Seitenvorlage oder Design-Token). Nur was sich nicht zuordnen lässt, gilt als
neu. Die verbindlichen Quellen im Code stehen jeweils unter „Quelle".

Stand: Oktober 2026. Bei Abweichungen gilt der Code.

---

## 1. Inhaltstypen

Jeder Inhaltstyp hat eine eigene Adresse, eine Eingabemaske, einen Standardaufbau
und wählbare Gestaltungsvarianten (Skins).

| Inhaltstyp | Adresse | Standardaufbau | Gestaltungsvarianten |
| --- | --- | --- | --- |
| Ausstellung | `/ausstellungen/…` | Leitfrage, Kapitel, Galerie | Standard, Quellenbühne, Objektalbum, Typografisch, Chronik |
| Rückblick | `/rueckblick/…` | Einleitung, Fließtext, Galerie | Standard, Quellenbühne, Objektalbum, Typografisch, Chronik |
| Projekt | `/projekte/…` | Kapitel, Merkpunkte, Galerie | Dossier, Standard, Typografisch, Bühne; Farbwelten Papier/Schwarz, Neon, Ocker/Blau |
| Veranstaltung | `/veranstaltungen/…` | Einleitung, Programm | Standard, Typografisch, Bühne |
| Führung | `/fuehrungen/…` | Kapitel, Galerie, Termine, Buchung | Routendossier, Kompakt, Standard |
| Publikation | `/publikationen/…` | Buch, Broschüre, Longread, Fotoalbum, Zeitleiste | Standard, Bildmatrix, Longread Poster |
| Ort in Schöneweide | `/schoeneweide/orte/…` | Einleitung, Epochen, Gegenwart | Standard, Ortsdossier |
| Person / Organisation | `/profile/…` | Profil mit Verknüpfungen | — |
| Video | `/video/…` | Player, Transkript, Playlists | — |
| Team | `/team/…` | Teammitglied mit Rolle | — |
| Archivobjekt | `/archivobjekte/…` | Objekt mit Metadaten, Bildern, Beziehungen | — |
| Archivsammlung | `/archivsammlungen/…` | Sammlung mit Objekten | — |
| Seite | frei | Einführung und Überblick, Text mit Bildern | Standard, Typografisch, Dossier |

**Veranstaltungen haben drei Zeitformen**, die den Seitenaufbau bestimmen:
Einzeltermin, Programm/Fest (Zeitraum) und Serientermin. Die Veranstaltungsart
(Vortrag, Gespräch, Lesung, Präsentation, Workshop, Konzert, Film, Repair Café) ist
nur eine Kennzeichnung, keine eigene Gestaltung.

**Ordnungen (Taxonomien):** Ausstellungstyp (Dauer-, Sonder-, Digitale Ausstellung),
Thema (Elektropolis, WF, KWO, TRO, Transformation, Arbeits- und Sozialgeschichte u. a.),
Sammlungsbereich, Veranstaltungsart, Führungstyp, Projektstatus, Videokategorie,
Publikationstyp und -thema.

Quelle: `plugins/iss-content/includes/post-types.php`,
`plugins/iss-content/includes/veranstaltungen-registry.php`,
`plugins/iss-content/includes/editorial.php`,
`themes/industriesalon/includes/editorial-render.php`

---

## 2. Bausteine (Gesten) und ihre Darstellungen

Redaktionelle Seiten werden aus Bausteinen zusammengesetzt. Jeder Baustein hat
feste Darstellungen. Die gewählte Gestaltungsvariante (Kapitel 3) bestimmt, wie
alle Bausteine einer Seite aussehen. Es wird nicht jede Seite einzeln gestaltet.

**Text**

| Baustein | Wofür | Darstellungen |
| --- | --- | --- |
| Einleitung | Inhalt kurz einordnen | — |
| Überschrift & Intro | Überschrift, Einleitung oder Leitfrage hervorheben | Leitstatement, Leitfrage, Handlungsaufruf |
| Kapitel | Thema mit Überschrift vertiefen | Kapitel, Hintergrund/Exkurs, Aufklappbar; Bilder im Text oder rechts daneben |
| Fließtext | Zusammenhängender Text | Standard, Erzählung links/Titel rechts, Titel links/Erzählung rechts |
| Zitat | Stimme mit Quelle | Zitat, Quellenauszug; in Ausstellungen zusätzlich Ausstellungsstimme, Quellenstation |
| Merkpunkte | Fakten und Zahlen | — |
| Programm | Ablauf eines Programms oder Fests | — |
| Abschluss | Einordnung, Kontakt, Links | — |
| Quelle & Rechte | Quellen, Rechte, Hinweise | — |

**Bild und Medien**

| Baustein | Wofür | Darstellungen |
| --- | --- | --- |
| Bildergalerie | Bilder mit Beschreibungen | Raster, Strecke, Bilderwand, Vollbild |
| Bild & Text | Thema mit Bild, Text, Kernfakten | Bild mit Infokasten, Bild neben Text, Titel auf Bild, Zweiteilige Herkunftserzählung, Seitenauftakt |
| Text-Bild-Reihe | Bilder und Texte als Reihe | Großformatig, Kompakt, Chronologisch, Themenspalten, Programm/Chronik |
| Bildbühne | Großes Auftaktbild einer Führung | — |
| Fotoalbum | Bildfolge aus Archiv- oder Redaktionsset, mit Seiten und Blättern | — |
| Archivobjekte | Ausgewählte Sammlungsobjekte im Zusammenhang | — |
| Begleitende Dateien | Downloads und Links | — |

**Navigation und Verknüpfung**

| Baustein | Wofür | Darstellungen |
| --- | --- | --- |
| Linkkarten | Einstiege zu weiteren Inhalten | Karten, Linkliste, Feature-Leiste, Themenpfade, Atlas-Ortskarten |
| Automatische Inhalte | Inhalte aus Datenquellen (Termine, Verwandtes) | — |
| Atlas-Karte | Orte und räumliche Zusammenhänge | — |
| Karte & Bild | Karte mit Ortsbild und Erläuterung | — |
| Lesenavigation, Lesekapitel | Navigation in langen Publikationen | — |
| Zeitleisten-Station | Zeitpunkt mit Text und Bild | — |

**Orte und Beteiligung**

| Baustein | Wofür |
| --- | --- |
| Epoche, Gegenwart | Geschichte und heutige Situation eines Ortes |
| Material beitragen | Aufruf an Gäste, Fotos und Dokumente zur Prüfung einzureichen |

Quelle: `plugins/iss-content/includes/editorial-vocabulary.php`

---

## 3. Gestaltungsvarianten (Skins)

Eine Gestaltungsvariante ist eine Entscheidung pro Seite, die das Aussehen aller
Bausteine festlegt: Satz, Bildbehandlung, Navigation am Rand.

| Variante | Charakter | Navigation | Für |
| --- | --- | --- | --- |
| Standard | Neutral, ausgewogen | — | alle |
| Typografisch | Schriftbetont, ruhig, wenig Bild | keine | Ausstellung, Rückblick, Projekt, Veranstaltung, Seite |
| Quellenbühne | Dokumente und Quellen im Vordergrund | keine | Ausstellung, Rückblick |
| Objektalbum | Objekte und Fotos wie in einem Album | keine | Ausstellung, Rückblick |
| Chronik | Zeitliche Abfolge | Abschnittsindex rechts | Ausstellung, Rückblick |
| Dossier | Arbeitsmappe mit Kapiteln | Sprungnavigation links bzw. oben | Projekt, Seite |
| Bühne | Großformatiger Auftritt | keine | Projekt, Veranstaltung |
| Bildmatrix | Bildraster | Abschnittsindex oben | Publikation |
| Longread Poster | Langer Lesetext mit Plakatauftakt | — | Publikation |
| Routendossier, Kompakt | Führung als Route oder kurz | — | Führung |
| Ortsdossier | Ortsgeschichte | — | Ort |

Quelle: `themes/industriesalon/includes/editorial-render.php`,
`themes/industriesalon/assets/css/skins/`

---

## 4. Funktionen

| Funktion | Was sie leistet | Zuständig |
| --- | --- | --- |
| Programm und Kalender | Alle Termine aus Veranstaltungen, Führungen, Ausstellungen in einem Kalender; Zeitleiste; Ausstellungsübersicht nach Verfügbarkeit | `iss-occurrences`, `iss-frontend` |
| Führungen buchen | Terminauswahl, Kapazität, Überbuchungsschutz, Buchungsprotokoll, Mitarbeiterbenachrichtigung | `iss-commerce-lite`, `iss-occurrences` |
| Gutscheine | Einlösen alter Gutscheine (kein Neuverkauf) | `iss-commerce-lite` |
| Anfragen und Bestellungen | Formulare für Anfragen, Raumvermietung, Publikationsbestellung mit Spamschutz und Verwaltung | `iss-commerce-lite`, `iss-publications` |
| Suche | Seitenweite Suche über alle Inhalte und Sammlungsobjekte, ohne externen Dienst | `iss-graph` |
| Verknüpfungen | Personen, Orte, Firmen, Objekte und Beiträge verbunden; „Verwandte Inhalte" automatisch | `iss-graph`, `iss-relations` |
| Atlas Schöneweide | Interaktive und statische Karten der Orte, Tour-Routen mit Stationen | `industriesalon-schoeneweide-register`, `iss-frontend` |
| Digitale Sammlung | Rund 11.000 Objekte, Sammlungen, Objektbrowser, Übernahme aus museum-digital | `iss-archive` |
| Publikationen | Bücher, Broschüren, Longreads, Fotoalben, Zeitleisten, Publikationsbrowser | `iss-publications` |
| Videothek | Videos mit Transkript, Playlists, Filtern, externen Quellen | `iss-content` |
| Gastbeiträge | Besucher reichen Material zur redaktionellen Prüfung ein | `iss-content` |
| Hinweise | Banner für Schließtage, Änderungen | `industriesalon-notices` |
| Vereinsdaten | Öffnungszeiten, Adresse, Kontakt zentral gepflegt, überall gleich angezeigt | `industriesalon-steuerung` |
| Newsletter | Anmeldung mit Bestätigung, ohne Tracking | `iss-newsletter` |
| Einwilligungen | YouTube und andere Dienste erst nach Zustimmung | `iss-consent` |
| Ausfallsicherheit | Kerninhalte bleiben als normale WordPress-Seiten sichtbar, auch wenn Erweiterungen ausfallen | `iss-content` (Vanilla Fallback) |
| Redaktionsoberfläche | Deutschsprachige Eingabe mit Vorschau, Bausteinauswahl, Versionen | `iss-editorial` |

Quelle: `docs/architecture/plugin-map.md`

---

## 5. Seitenvorlagen und Muster

**Übersichtsseiten:** Startseite, Ausstellungen, Veranstaltungen, Kalender,
Führungen, Projekte, Publikationen, Sammlungen, Archiv, Videos, Schöneweide,
Über uns, Verein, Repair Café, Salon-Vermietung, Suche.

**Wiederverwendbare Muster:** 3er- und 4er-Kartenreihe, 50/50 Bild-Text,
asymmetrisches Feature, Flex-Split, Landing-Hero mit Notiz, Newsletter mit Förderern,
Projektdossier-Kapitel, Ausstellungs-Arbeitsplatz, Objekt-Highlight,
Themenpfad-Leiste, Anerkennung, Mission/Unterstützung, Team-Raster,
Infopanels (Anmeldung, Besuch, Vermietung).

Quelle: `themes/industriesalon/templates/`, `themes/industriesalon/patterns/`

---

## 6. Designsystem

Alle Farben, Schriften und Abstände sind zentral festgelegt. Neue Gestaltung nutzt
diese Werte, statt eigene zu erfinden.

**Farben**

| Name | Wert | Rolle |
| --- | --- | --- |
| Scarlet Red | `#d51b23` | Akzent, Signalfarbe |
| Eerie Black | `#1e1e1e` | Text, dunkle Flächen |
| Vaporous White | `#f9f9fa` | Hintergrund |
| Cerebral Grey | `#c0b8b5` | Linien, ruhige Flächen |
| Turtle Green | `#579e7d` | Ergänzungsfarbe |
| Pale Rock Blue | `#869fba` | Ergänzungsfarbe |
| Sunrise Yellow | `#ebbc1e` | Ergänzungsfarbe |
| Russet Brown | `#753a18` | Ergänzungsfarbe |

**Schrift:** IBM Plex Sans (Grundschrift), IBM Plex Serif (Lese- und Auszeichnungstexte).
Sieben Größenstufen von 0,875 rem bis 3,4 rem.

**Bausteine der Gestaltung:** rund 70 Design-Tokens (`--iss-…`) für Flächen,
Abstände, Radien, Akzente und eine dreistufige Button-Hierarchie; gemeinsame
Layout-Grundformen (`primitives.css`); Lesespalte um 66 Zeichen mit
Bildüberstand für breite Medien.

Quelle: `themes/industriesalon/theme.json`,
`themes/industriesalon/assets/css/tokens.css`,
`themes/industriesalon/assets/css/primitives.css`

---

## 7. Wie ein Vorschlag auf diesen Katalog aufsetzt

1. Jedes Element des Vorschlags einem Eintrag oben zuordnen.
2. Was zuordenbar ist, wird mit den vorhandenen Teilen umgesetzt, oft ohne
   Programmierung, nur redaktionell.
3. Was nicht zuordenbar ist, ist der eigentliche Vorschlag. Er wird kurz
   beschrieben: Was soll es leisten, welcher Inhaltstyp nutzt es, warum reicht
   keine vorhandene Darstellung?
4. Eine neue Darstellung für einen vorhandenen Baustein ist fast immer günstiger
   als ein neuer Baustein. Ein neuer Baustein ist fast immer günstiger als eine
   eigene Website.
