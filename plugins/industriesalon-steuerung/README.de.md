# Industriesalon Steuerung

Diese Anleitung ist für die Redaktion und alle, die Inhalte im WordPress-Admin pflegen.

## Wofür ist das Plugin da?

`Industriesalon Steuerung` ist die zentrale Stelle für wiederverwendbare Besuchs- und Basisinformationen.

Hier pflegen Sie:

- Standort und Adresse
- Kontakt
- Google Maps / Anfahrt
- Besuchszeiten
- Bürozeiten
- Sondertage
- Preise
- Barrierefreiheit
- häufige Fragen
- Mission Statement

Die Angaben werden danach automatisch an verschiedenen Stellen der Website ausgegeben.

Das wichtigste Prinzip:

- Daten nur hier pflegen
- nicht dieselben Angaben zusätzlich in einzelnen Seiten eintragen

## Wo finde ich das Plugin?

Im WordPress-Admin:

- `Industriesalon Steuerung`

Dort finden Sie oben Reiter für die einzelnen Bereiche.

## Was sind normale Zeiten und was sind Sondertage?

Es gibt zwei Arten von Zeitangaben:

- `Besuchszeiten`
  Das sind die normalen regulären Öffnungszeiten für Besucherinnen und Besucher.

- `Bürozeiten`
  Das sind interne oder organisatorische Zeiten, die getrennt gepflegt werden können.

- `Sondertage`
  Das sind Abweichungen für ein bestimmtes Datum.

Beispiel:

- regulär: `Do–So 14–18 Uhr`
- Sondertag: `01.05. geschlossen`
- Sondertag: `12.05. Sonderöffnung 16–20 Uhr`

## Sondertage richtig verwenden

Jeder Sondertag hat jetzt eine klare Bedeutung.

Sie wählen für jedes Datum eine `Art des Sondertags`:

- `Geschlossen`
  Der Ort ist an diesem Datum geschlossen.

- `Sonderöffnung`
  Ein zusätzlicher oder abweichender Öffnungstermin.

- `Länger geöffnet`
  Der Ort ist länger als sonst offen.

- `Verkürzte Zeit`
  Der Ort ist offen, aber kürzer als sonst.

- `Veranstaltungstag`
  Es gibt einen besonderen Veranstaltungstag. Das kann normale Besuche beeinflussen.

- `Nur nach Vereinbarung`
  Kein freier Besuch, nur mit Absprache.

Diese Auswahl ist wichtig, weil die Website daraus automatisch passende Texte bildet:

- `Heute geschlossen`
- `Heute Sonderöffnung`
- `Heute länger geöffnet`
- `Heute nur nach Vereinbarung`

## So tragen Sie reguläre Öffnungszeiten ein

Im Reiter `Besuchszeiten`:

1. Für jeden Wochentag `von` und `bis` eintragen.
2. Falls geschlossen: Haken bei `Geschlossen`.
3. Optional: kurzer Hinweis pro Wochentag.
4. Optional: `Globaler Hinweis` für die ganze Gruppe.

Beispiel:

- Donnerstag `14:00` bis `18:00`
- Freitag `14:00` bis `18:00`
- Samstag `14:00` bis `18:00`
- Sonntag `14:00` bis `18:00`

## So tragen Sie einen Sondertag ein

Im Reiter `Besuchszeiten` im Abschnitt `Sondertage`:

1. `Sondertag hinzufügen`
2. Datum auswählen
3. Bereich wählen:
   - `Besuchszeiten`
   - `Bürozeiten`
   - `Beides`
4. `Art des Sondertags` wählen
5. Falls nötig Zeit von/bis eintragen
6. Optional `Kurzes Label` ergänzen
7. Optional `Hinweis` ergänzen

### Wofür ist das kurze Label?

Das kurze Label überschreibt die automatische Kurzbezeichnung in Listen oder Statuszeilen.

Beispiele:

- `Ferienöffnung`
- `Nur Abendprogramm`
- `Aufbau`

Bitte sparsam verwenden. Meist reicht die gewählte Art des Sondertags.

### Wofür ist der Hinweis?

Der Hinweis ist ein ergänzender kurzer Satz für Menschen, die mehr Kontext brauchen.

Beispiele:

- `Nur Erdgeschoss zugänglich`
- `Einlass nur bis 17 Uhr`
- `Besuch parallel zur Veranstaltung möglich`

## Wie werden die Daten auf der Website genutzt?

Die Website kann dieselben Daten in verschiedenen Formen anzeigen:

- als kurze Fußzeile
- als Karte auf der Startseite
- als Info-Panel
- als ausführliche Tabelle
- als kurze Statuszeile wie `Heute geöffnet`

Sie müssen dafür nichts extra einstellen.

Wichtig ist nur:

- Zeiten korrekt pflegen
- Sondertage semantisch korrekt auswählen

## Was Sie nicht tun sollten

Bitte nicht:

- Öffnungszeiten direkt in Seitentexte schreiben
- dieselbe Adresse an mehreren Stellen manuell pflegen
- Sonderfälle als freien Fließtext ohne Datum eintragen
- Sondertage mit falscher Art anlegen, nur um einen Text zu erzwingen

## Gute Arbeitsweise

Vor einer Änderung:

1. Prüfen, ob es eine reguläre Änderung oder ein Sondertag ist.
2. Bei größeren Änderungen zuerst sichern.

Nach einer Änderung:

1. Speichern
2. Seite im Frontend prüfen
3. Bei Zeitangaben besonders Status und Sondertage anschauen

## Sicherung und Wiederherstellung

Im Reiter `Werkzeuge` können Sie:

- eine JSON-Sicherung exportieren
- eine vorhandene Sicherung importieren

Empfehlung:

- vor größeren Änderungen exportieren
- nach dem Import eine Frontend-Seite prüfen

## Typische Beispiele

### Feiertag geschlossen

- Datum wählen
- Bereich: `Besuchszeiten`
- Art: `Geschlossen`
- keine Uhrzeiten eintragen

### Nur kürzer geöffnet

- Datum wählen
- Bereich: `Besuchszeiten`
- Art: `Verkürzte Zeit`
- z. B. `14:00` bis `16:00`

### Längerer Veranstaltungsabend

- Datum wählen
- Bereich: `Besuchszeiten`
- Art: `Veranstaltungstag`
- z. B. `14:00` bis `20:00`
- optional Hinweis: `Ab 18 Uhr Konzertbetrieb`

### Büro geschlossen, Museum offen

- Datum wählen
- Bereich: `Bürozeiten`
- Art: `Geschlossen`

## Wenn etwas im Frontend falsch aussieht

Bitte zuerst prüfen:

1. Wurden die Änderungen gespeichert?
2. Wurde der richtige Bereich gewählt?
3. Ist der Sondertag auf das richtige Datum gesetzt?
4. Wurde die richtige Art des Sondertags gewählt?
5. Seite neu laden

Wenn es danach noch falsch ist:

- technische Betreuung informieren
- am besten mit Datum, Bereich und genauer Beobachtung

## Kurzfassung

Merksatz:

- regelmäßige Zeiten in `Besuchszeiten` oder `Bürozeiten`
- Ausnahmen als `Sondertage`
- keine doppelten Angaben in Seiteninhalten
