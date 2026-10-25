# Für den Vorstand: Was ist dieses System?

Kurzüberblick über die Website und digitale Sammlung des Industriesalon
Schöneweide e.V. für Vorstandsmitglieder ohne Programmierkenntnisse.

## In einem Satz

Eine WordPress-Installation mit eigenen Erweiterungen (Plugins), die Website,
Veranstaltungen, Führungen, Buchungen, Publikationen, Orte in Schöneweide und
rund 11.000 Sammlungsobjekte aus **einer** gemeinsamen Datenbasis bedient.

## Warum das wichtig ist

Alle Inhalte hängen zusammen: Ein Objekt aus der Sammlung kann mit einem Ort,
einer Person, einer Ausstellung und einer Führung verknüpft sein. Diese
Verknüpfungen funktionieren nur, solange es **eine Quelle der Wahrheit** gibt.
Jede separate Website, Tabelle oder App, die dieselben Inhalte noch einmal
speichert, erzeugt Widersprüche, die später niemand mehr auflöst.

Deshalb gilt im Projekt die Regel „keine parallelen Systeme"
([no-parallel-systems.md](../agent/no-parallel-systems.md)).

## Wer ist wofür zuständig?

| Bereich | Zuständiger Teil | Was er macht |
| --- | --- | --- |
| Aussehen der Website | Theme `themes/industriesalon` | Seitenlayouts, Gestaltungsvarianten (Skins), Typografie, Farben |
| Inhalte und Redaktion | `iss-content`, `iss-editorial` | Inhaltstypen, Eingabemasken, redaktionelle Bausteine |
| Sammlung und Archiv | `iss-archive` | Sammlungsobjekte, Import, Weitergabe an museum-digital, DDB, Europeana |
| Zusammenhänge | `iss-graph`, `iss-relations` | Personen, Orte, Firmen und ihre Verknüpfungen; Suche |
| Termine | `iss-occurrences`, `iss-frontend` | Programm, Kalender, Führungstermine |
| Buchungen und Anfragen | `iss-commerce-lite` | Buchungen, Gutscheine, Anfragen, Benachrichtigungen |
| Publikationen | `iss-publications` | Bücher, Broschüren, Alben |
| Orte in Schöneweide | `industriesalon-schoeneweide-register` | Ortsregister und Atlas |
| Stammdaten des Vereins | `industriesalon-steuerung` | Öffnungszeiten, Adresse, Kontakt, Hinweise |
| Newsletter, Datenschutz | `iss-newsletter`, `iss-consent` | Anmeldung mit Bestätigung, Einwilligungen |

Die genaue, verbindliche Zuständigkeitskarte steht in
[plugin-map.md](../architecture/plugin-map.md).

## Wie bringe ich einen Vorschlag ein?

1. **Beschreiben, nicht bauen.** Was soll Besucherinnen, Besuchern oder dem
   Verein ermöglicht werden? Ein kurzer Text oder eine Skizze genügt. Ein
   KI-generiertes Design ist als Anschauung willkommen, aber noch keine
   Entscheidung.
2. **Mit KI prüfen lassen, aber mit der festen Vorlage.** Den
   [KI-Prüfauftrag](ki-pruefauftrag.md) verwenden. Er zwingt die KI, zuerst den
   vorhandenen Code zu lesen und zu sagen, was es schon gibt.
3. **Ergebnis vollständig teilen** mit dem Vorstand und der technischen Leitung.
4. **Gemeinsam entscheiden**, ob der Vorschlag im Bestand umgesetzt wird, als
   abgegrenzte Erweiterung entsteht oder zurückgestellt wird.

Separate Projekt-Websites außerhalb dieses Systems sind möglich, brauchen aber
vorher abgestimmte Regeln für den Datenaustausch, damit Inhalte nicht doppelt
gepflegt werden.

## Bekannte Grenzen

Ehrlich benannt, damit sie nicht erst durch eine KI-Prüfung auffallen:

- Das System ist umfangreich, und das Wissen darüber liegt derzeit überwiegend
  bei einer Person. Dieses Risiko wird durch Dokumentation verringert, aber nicht
  beseitigt. Es spricht dafür, das Wissen breiter zu verteilen, nicht dafür,
  ein zweites System daneben aufzubauen.
- Im Repository liegen einige Fremd-Plugins (z. B. Media Library Assistant,
  Newsletter, Query Monitor). Sie gehören nicht zur Eigenentwicklung und lassen
  das Projekt größer wirken, als es ist.
- Die Dokumentation ist vor allem für Entwickler und KI-Werkzeuge geschrieben.
  Dieses Dokument ist der Einstieg für alle anderen.

## Weiterlesen

- [KI-Prüfauftrag](ki-pruefauftrag.md): Kopiervorlage für KI-Werkzeuge
- [Dokumentationsübersicht](../README.md) (englisch, technisch)
- [AGENTS.md](../../AGENTS.md): Regeln für Entwickler und KI-Agenten
