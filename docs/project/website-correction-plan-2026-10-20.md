# Korrekturplan für die Industriesalon Website

Stand 20. Oktober 2026. Zusammenführung der beiden Websiteaudits mit Gegenprüfung auf [Staging](https://staging.industriesalon.info/).

**Zuerst müssen öffentliche Arbeitsnotizen, widersprüchliche Besuchsangaben und der horizontale Seitenüberlauf beseitigt werden. Danach folgen vollständige, verständliche Texte und gezielte Verbesserungen der gemeinsamen Gestaltung.** Ein umfassender Neuentwurf ist dafür nicht erforderlich.

Der Plan umfasst Texte, Bilder und Gestaltung sowie funktionale und datenbezogene Fehler. Er beschreibt die nächsten Korrekturen; er erteilt keine Freigabe für ungeprüfte Sachangaben oder eine vollständige Neugestaltung.

## Umsetzung — Programm und erstes Korrekturpaket

Stand 20. Oktober 2026, nach der Bestätigung der Führungsangaben. Das erste Korrekturpaket und die Programmkorrekturen sind lokal geprüft und für die gemeinsame Code-/Datenveröffentlichung vorbereitet. Der übrige Plan bleibt offen; der aktuelle Veröffentlichungsstand steht im [Handoff](../../handoff_CURRENT.md).

| Paket | Lokales Ergebnis |
| --- | --- |
| F01 | TXL-Arbeitsnotiz als Entwurf erhalten; direkter öffentlicher Aufruf liefert 404, Suche ohne Treffer. Publikationsreihe enthält ausschließlich Publikationen. |
| T01/T02 | Vollständige Startseiten-/Teamtexte, vollständige Fördererzeile, „Archive: Theme“ entfernt. Technischer Download im redaktionellen Papierkorb erhalten. Doppeltes „Fotoalbum“ im Kicker beseitigt. |
| F02 | Elektropolis öffentlich: 14 €, ermäßigt 10 €, etwa 2 Stunden, Kalenderbuchung. Separates Gruppenangebot aus der privaten Altfassung wiederhergestellt: nur Anfrage, 120 € bis 7 Personen, weitere Personen 14 €/10 €. Katalogzuordnung und Anfrageberechtigung folgen diesen getrennten Angeboten. Verwandte Karten erkennen die aktuellen Termindaten wieder. |
| F03/F05 | Startseitenempfehlung heißt „Unser Tipp“. Behrensbau-Hinweise nennen ausdrücklich geschlossene Innenbesichtigungen mit Stand 20.10.2026. Reguläres Licht und Transparenz bleibt bei etwa 2 Stunden; der externe 90-Minuten-Termin am 20.10. ist ausdrücklich ein Spezial mit Berliner Unterwelten. Seine Zeit wurde nicht überschrieben. |
| F04 | „Aktuell im Haus“ zeigt laufende Sonderausstellungen einschließlich „Jüdisches Leben und Arbeiten“. Dauer- und Digitalausstellungen bleiben in ihren eigenen Reitern. Laufzeitgrenzen und Wechsel ins Archiv getestet. Weitere Besuchsinformationen bleiben offen. |
| V01/F08 | Überlaufursachen im gemeinsamen Banner und Bildrahmen beseitigt. Eingebettete Bildkarten fangen das Mausrad nicht mehr zum Zoomen ab; Zoomschaltflächen bleiben erhalten. |

Die aktuelle SuperSaaS-Antwort bestätigt die ausgebuchten nächsten Termine und verfügbare spätere Dezembertermine. Keine Kapazitäten, externen Termine oder Buchungen wurden verändert. Die Bedeutung von `count` wurde mit der [SuperSaaS-Dokumentation](https://www.supersaas.com/info/dev/appointment_api) abgeglichen.

**F02 ergänzt, lokal geprüft:** Der gemeinsame Anfrage-Dialog bietet reguläre und ermäßigte Tickets, einschließlich rein ermäßigter Anfragen. Für die öffentliche Elektropolis sind 14 €/10 € hinterlegt. Die Gesamtanzahl wird gegen die verfügbaren Plätze geprüft; der Server berechnet den Gesamtpreis aus den gespeicherten Preisen. Ticketaufteilung und Preis werden gespeichert und in der Verwaltung, Benachrichtigung und im CSV-Export weitergegeben. Wiederholte Übertragung derselben Anfrage erzeugt keinen zweiten Eintrag und keine zweite Benachrichtigung. Gruppentermine bleiben ein separates Anfrageangebot.

**F02 abgeschlossen im Programmpaket:** Licht und Transparenz hat getrennte öffentliche und Gruppenangebote; die Gruppenführung ist im Gruppenkatalog sichtbar und nur auf Anfrage buchbar. Öffentliche Licht- und Kiez-Termine übernehmen die bereits veröffentlichten 14 €/10 €, die Bustour 25 €. Veraltete Mai-Daten und doppelte Einleitungen sind aus Kiez-/Bustour entfernt; aktuelle Termine kommen aus dem Kalender. Drei individuelle beziehungsweise interne Reservierungen bleiben als Quelldatensätze erhalten, erscheinen jedoch nicht mehr als öffentliche Termine. Spezialtitel und tatsächliche Zeitspannen bleiben bei Serienzuordnung, Kalenderanzeige und Buchungsanfrage erhalten. Ein isolierter wiederholter Sync prüft, dass ignorierte Serien nicht erneut öffentlich zugeordnet werden. Lange Tourtitel passen durch eine Korrektur im bestehenden Tourlayout auch auf schmale Bildschirme.

Zusatzprüfung Ermäßigung: 160 isolierte Commerce-Prüfungen mit abgefangener Mail und unveränderten Echtdaten, sieben JS-Testfälle, 26 native Buchungsprüfungen; PHP-Syntax, PHPCS/PHPStan und ESLint bestanden. Chrome: 1 regulär + 2 ermäßigt = 34 €, 2 ermäßigt = 20 €, Null-/Kapazitätsgrenzen, Desktop und 390 px ohne horizontalen Überlauf; getrennte Gruppenanfrage und Preisfelder im Editor geprüft. Keine echte Anfrage abgesendet. Reversibles Datenupdate: `2026-10-20-reduced-tickets.php`, nach `tour-facts`; privater Input und Vorherstand unter `var/corrections-20261020/`, Receipt `iss_backup_20261020_reduced_tickets`. Kein Schema- oder Uploadtransfer erforderlich.

Prüfung: 42 Audit-, 26 Programmvorschau- und 26 native Buchungsprüfungen; PHP-Syntax, PHPCS/PHPStan und gezielte CSS-/JS-Prüfungen bestanden. Blockserialisierung und Templateautorität geprüft. Chrome: getrennte Kataloge, öffentlicher Termindialog und Gruppenanfrage ohne Absenden; kein Seitenüberlauf auf Startseite, Über uns, Sammlungen, Schöneweide, Führungsübersicht und neuer Gruppenseite bei 390/768/1348/1720 px. Öffentliche HTTP-Prüfung: elf erreichbare Routen und erwartete 404 der Arbeitsnotiz. Manuelle Mausrad-/Touch-, Firefox-, Zoom- und vollständige Editorabnahme bleiben offen.

Private Daten und Vorherzustände liegen unter `var/corrections-20261020/`. Für Staging in dieser Reihenfolge vorbereiten: `ops/migrations/2026-10-20-public-copy-cleanup.php` mit `copy-cleanup-input.json`, danach `ops/migrations/2026-10-20-tour-facts.php` mit `tour-facts-input.json`. Zielzustand und URLs zuerst abgleichen; IDs nicht blind übernehmen. Die zweite Migration verfeinert dasselbe Elektropolis-Dokument, daher die erste unmittelbar vor der zweiten verifizieren. Danach folgen `2026-10-20-reduced-tickets.php` und `2026-10-20-programme-corrections.php` mit ihren privaten Eingaben. Die Migrationen bewahren private Entwürfe und lokale Vorherbilder; die vierte speichert zusätzlich die bisherigen Serien-, Termin- und Projektionszeilen. 29 isolierte Occurrence-Prüfungen bestanden einschließlich zweier aufeinanderfolgender Syncs mit abgefangenen HTTP-Aufrufen. Das Gruppenbild ist ein vorhandenes Upload; keine neue Mediendatei oder Schemaänderung erforderlich. Datenpakete bleiben außerhalb von Git.

## Grundlage und Prüfstand

| Quelle | Umfang | Verwendung |
| --- | --- | --- |
| **A** — `Staging-Audit industriesalon.info.md`, 20.10.2026 | Elf Desktopansichten einschließlich Menü und Footer; keine mobile Prüfung | Detaillierte Gestaltungsbefunde, zehn als Blocker bezeichnete Punkte, Textvorschläge und Platzhalterliste |
| **B** — `Industriesalon_Website_Audit_2026-10-20.docx` | Zwanzig Seiten auf Desktop; zusätzlich Projekte, Kalender, Ausstellungen, Publikationen und Videos | Breiterer redaktioneller Abgleich, zehn nummerierte Fehler, Datenwidersprüche und Abnahmekriterien |
| **Gegenprüfung** — 20.10.2026 | Alle zwanzig verlinkten Seiten aus B per HTTP erreichbar; gezielte Browserprüfung von Startseite, Führungen, Elektropolis, Ausstellungen, Archivobjekt, Schöneweide und Über uns | Bestätigung und Eingrenzung der Befunde; zusätzliche mobile Stichprobe |

Lokaler Websitecode und Staging stehen bei der Prüfung auf `c3bb60ed7eaf28dbc99e362dc3b3dc0a55b2769e`. Die HTTP-Prüfung bestätigt Erreichbarkeit und ausgelieferte Texte, keine vollständige Funktionsabnahme. Formulare wurden nicht abgesendet, Buchungen nicht ausgelöst. Eine erneute vollständige Prüfung aller Seiten auf Mobilgeräten sowie mit Tastatur und Screenreader bleibt Teil der Abnahme.

**Statusangaben im Plan:** „bestätigt“ bedeutet aktuell in Browser, ausgeliefertem Text oder genanntem Code belegt; „Prüfauftrag“ bezeichnet einen Auditbefund mit noch offener Ursache oder Reichweite; „Entscheidung“ bezeichnet eine redaktionelle oder gestalterische Wahl. Vermutete technische Ursachen aus A werden nicht als erwiesen übernommen.

## Was der Abgleich verändert

| Thema | Ergebnis der Gegenprüfung | Konsequenz |
| --- | --- | --- |
| Buchungsstatus | Die vier öffentlichen Führungskarten zeigen jeweils den nächsten Termin als ausgebucht. Die Startseite bietet „Termin wählen“; die Elektropolis-Seite zusätzlich einen allgemeinen externen Buchungslink. Ob tatsächlich alle späteren Termine ausgebucht sind, ist damit nicht belegt. | Darstellung und Verfügbarkeitsquelle zusammen prüfen. Weder Plätze freigeben noch Termine entfernen, nur um die Oberfläche konsistent aussehen zu lassen. |
| Gruppenführungen | Im Reiter „Gruppenführungen“ stehen elf Angebote. Der zusätzliche Abschnitt „Führungen für Gruppen und besondere Interessen“ enthält dagegen keine Angebote. | Den leeren beziehungsweise redundanten Abschnitt korrigieren; keine zweite Angebotsliste aufbauen. |
| Führungen auf Mobilgeräten | Bei 1348 px Viewport: 1333 px Inhaltsbreite und 1382 px Dokumentbreite. Bei 390 px Viewport: 375 px Inhaltsbreite und 1149 px Dokumentbreite, auch nach erneutem Lesen der geladenen Seite. | Hohe Priorität. Seitenüberlauf beheben; beabsichtigtes horizontales Scrollen muss auf die jeweilige Galerie begrenzt bleiben. |
| Atlaszahlen | `atlas/places.js` weist bei `ERA_FILTER_CONTEXT_ONLY` jeder Epoche die Gesamtzahl zu; `atlas/store.js` lässt dabei alle Orte erhalten. Das ist ausdrücklich implementiertes Kontextverhalten. | Kein erwiesener Rechenfehler. Gleiche Zahlen nicht als historische Trefferzahlen darstellen. Bedeutung der Epochenauswahl klären, bevor das Verhalten geändert wird. |
| Kartenzoom | Der interaktive Atlas setzt `scrollWheelZoom: false`. Der separate Bildkarten-Renderer setzt `scrollWheelZoom: !preview`. | Den Scrollkonflikt gezielt bei eingebetteten Bildkarten lösen; keine pauschale Reparatur aller Karten. |
| „Heute geschlossen“ | Besuchszeiten sind Do–So, 14–18 Uhr. Ein Dienstagstermin einer Führung beweist keine Öffnung des Museums. | Museumsöffnung, Bürozeiten und Termine verständlich auseinanderhalten. Nicht automatisch „geöffnet“ setzen, sobald irgendein Termin existiert. |
| Laufende Ausstellung vor Oktoberterminen | Die Startseite kennzeichnet die Ausstellung ausdrücklich mit „Läuft bis 7. November“. | Nicht als bewiesenen Sortierfehler behandeln. Laufende Ausstellungen räumlich oder sprachlich von der Folge einzelner Termine trennen. |
| Archivumfang und erste Ergebnisse | Aktuell werden „3053 Archivobjekte“ angezeigt. Die erste Ergebnisgruppe besteht bei dieser Prüfung nicht aus zwanzig gleichartigen Schaltbildern. A nennt außerdem ungefähr 11.000 Objekte ohne bestätigte Bezugsgröße. | Keine dieser Zahlen als Gesamtbestand veröffentlichen. Geltungsbereich jeder Zahl benennen; die konkrete Startauswahl erneut beurteilen. |
| Bereits vorhandene Handlungen | Die drei Besuchskarten auf der Startseite sind bereits als ganze Karten verlinkt. Die Vermietungsseite enthält „Anfrage stellen“, eine Mailadresse und eine Telefonnummer. | Keine Doppelimplementierung. Sichtbarkeit und Formulierung verbessern; ein neues Anfrageformular ist eine Produktentscheidung. |
| Wiederholter Elektropolis-Titel | Der Hero verwendet H1, der wiederholte Titel im Fließtext H2. | Redaktionelle Doppelung prüfen; kein aktuell belegter zweiter H1. |
| Schöneweider Kartenreihenfolge | DOM-Beschriftungen der Marker lauten aktuell 1 Behrensbau, 2 Industriesalon, 3 Kabelwerk und entsprechen der Kartenreihenfolge. | Der pauschale Reihenfolgefehler ist aktuell nicht reproduziert. Geografische Zuordnung und sichtbare Markerpositionen trotzdem gezielt gegenprüfen. |
| Rückblick ohne Bericht | Anne Rabe zeigt „Zum Rückblick“ und anschließend „noch kein Bericht“. Die automatische Kennzeichnung vergangener Veranstaltungen ist bereits gewollte Funktion. | Den Link dem vorhandenen Material anpassen; die automatische zeitliche Einordnung beibehalten. |
| Einheitlichkeit der Gestaltung | Beide Audits empfehlen weniger Varianten. A fordert eine Serifenschrift und zwei Kartentypen; B lässt rollenabhängige Typografie und vier konzeptionelle Layoutfamilien zu. | Gemeinsame Bedienung und klare Gestaltungsrollen festlegen. Keine starre Anzahl von Fonts, Farben, Karten oder neuen Templates ableiten. |
| Rechtliche Links und URL-Schreibweisen | Sprungmarken unter `/verein/` und ältere Slugs sind vorhanden. Allein diese Form belegt keinen technischen Defekt. | Erreichbarkeit und Verständlichkeit prüfen. Eigene Rechtstextseiten beziehungsweise neue Slugs nur mit begründetem Nutzen und Weiterleitungen planen. Keine rechtliche Bewertung aus der URL-Form ableiten. |

**Erhalten bleiben:** die realen Orts- und Menschenfotos, die verständlichen Besuchsangebote, die Schöneweide-Zeitleiste, die vorhandenen Projektseiten sowie Quellen- und Bildnachweise. Auch Planungsstatus und die Futura-Kennzeichnung „KI-generierte Bildidee“ bleiben sichtbar, solange sie zutreffen.

Die zuvor korrigierten Projekttitel und das Futura-Logo sind auf Staging vorhanden. Das Über-uns-Triptychon steht auf Desktop bei etwa 252 px von oben mit drei ungefähr 547 px großen quadratischen Bildern; alle drei laden. Die mobile Stichprobe zeigt gestapelte Bilder ohne Seitenüberlauf. Diese Änderungen bleiben erhalten. Die von beiden Audits vorgeschlagene Abschaffung der Papierkarten ist eine gesonderte Designentscheidung.

## Reihenfolge und Zuständigkeit

P0 blockiert die nächste öffentliche Freigabe. P1 ist die anschließende Qualitätsarbeit; einzelne Kontrast- oder Bedienfehler werden zu P0, wenn sie tatsächlich Inhalte oder Handlungen unzugänglich machen. P2 bezeichnet optionale Weiterentwicklung.

| Etappe | Arbeitspaket | Federführung | Fertig wenn |
| --- | --- | --- | --- |
| 1 | Öffentliche Arbeitsreste und grobe Inhaltsfehler entfernen — F01, T01, T02 | Redaktion und zuständiger Renderer | Keine interne Reservierung, technischen Dateitypen oder unvollständigen Sätze an den belegten Stellen |
| 2 | Termine, Buchung, Ausstellungen und Projektstatus abgleichen — F02 bis F06 | Programmredaktion, Datenverantwortliche und Entwicklung | Derselbe Inhalt zeigt auf allen betroffenen Seiten dieselben bestätigten Fakten und eine passende Handlung |
| 3 | Überlauf und verdeckte Bedienung beheben — V01, V02, F08 | Theme und bestehende Kartenkomponente | Seiten bleiben innerhalb des Viewports; Header und Karten blockieren weder Lesen noch Navigation |
| 4 | Seitentexte und fehlende Besucherinformationen bearbeiten — T03 bis T10 | Fachredaktion, Projekt- und Raumverantwortliche | Jede Seite beginnt mit einem konkreten Angebot und führt verständlich zum nächsten Schritt |
| 5 | Gemeinsame Gestaltung und Archivdarstellung verbessern — V03 bis V08, F07, F09 bis F11 | Gestaltung, Theme und jeweiliger Dateninhaber | Die vereinbarten Muster funktionieren auf repräsentativen Seiten und Geräten |
| 6 | Gesamtabnahme und freigegebene Veröffentlichung | Redaktion und Entwicklung | Die Abnahmeliste am Ende ist erfüllt; Code und erforderliche Inhalte sind gemeinsam geprüft |

Jede Etappe in kleine, separat überprüfbare Änderungen teilen. Die Gestaltung des Buchungskastens folgt den geklärten Angebotsarten und Preisen. Die große Textkürzung folgt der vereinbarten Seitenaufgabe. Keine pauschale Stundenschätzung aus den Audits übernehmen.

## Fehler und Daten

### F01 Öffentliche Arbeitsnotizen und falsche Inhaltslisten

**P0 · bestätigt · B Fehler 05 und 06.** Auf [Ausstellungen](https://staging.industriesalon.info/ausstellungen/) erscheinen unter „Publikationen“ Veranstaltungen und die interne Notiz „evtl. TXL-Projekt im Salon! … Zeitraum geblockt“.

Die Reservierung aus allen öffentlichen Ausgaben nehmen und den internen Sachverhalt erhalten. Beim Publikationsblock Quellenauswahl, Inhaltstyp und gegebenenfalls Relationen prüfen. Passende Publikationen ausgeben; bei fehlenden Treffern den Abschnitt ausblenden. Den internen Text nicht durch eine erfundene öffentliche Veranstaltungsbeschreibung ersetzen.

**Abnahme:** Die Notiz erscheint weder in dieser Liste noch über Suche, Kalender, verwandte Inhalte oder den direkten öffentlichen Aufruf. Unter „Publikationen“ stehen ausschließlich fachlich passende Publikationen.

### F02 Buchung und Verfügbarkeit

**P0 · sichtbare Widersprüche bestätigt, Datenursache offen · A Blocker 1 und 2; B Führungen.** [Führungsübersicht](https://staging.industriesalon.info/fuehrungen/), [Elektropolis](https://staging.industriesalon.info/fuehrungen/elektropolis-tour/), Startseite und verknüpfte Ortsseiten vermitteln unterschiedliche Zustände. Am Behrensbau steht bei Elektropolis „Aktuell keine Termine online“, obwohl die Führungsseite Oktobertermine zeigt.

Eine Prüftabelle je Angebot und konkretem Termin anlegen: Beginn, Ende, Angebotsart, Kapazität, verfügbare Plätze, Status, Preis und Buchungsziel. Unbekannte Kapazität, ausgebucht, abgesagt und nur auf Anfrage müssen getrennte Zustände bleiben. Die vorhandene Termin- und Verfügbarkeitsquelle verwenden. Öffentliche Teilnahme und Gruppenanfrage im Buchungskasten klar trennen; Gruppenpreise ausdrücklich benennen. Der externe allgemeine Buchungslink darf keine Verfügbarkeit des daneben gezeigten ausgebuchten Termins versprechen.

**Abnahme:** Ein freier, ein ausgebuchter, ein abgesagter und ein Termin mit unbekannter Kapazität sowie eine Gruppenanfrage werden korrekt dargestellt. Startseite, Übersicht, Kalender, Detailseite und verwandte Karten stimmen überein. Fachlich bestätigte Preise stehen vor dem Buchungswechsel. Kein Test löst eine reale Buchung oder Nachricht aus.

### F03 Termintexte und Zeitdarstellung

**P0 · bestätigt, teilweise fachlich zu klären · A Blocker 3 bis 5; B Fehler 03 und 04.** Der Bustourteaser nennt Mai neben einem Oktobertermin. Elektropolis nennt etwa 2,5 Stunden bei 14–16 Uhr; Licht und Transparenz etwa 2 Stunden bei 14–15.30 Uhr. „Als Nächstes“ bezeichnet das Repair-Café am 4. November, obwohl vorher andere Angebote erscheinen.

Vergängliche Daten aus dauerhaften Teasern entfernen; Datum und Uhrzeit aus der Terminquelle ausgeben. Dauern fachlich bestätigen. Empfehlung für die hervorgehobene Auswahl: **„Unser Tipp“**, wenn sie redaktionell ausgewählt ist. „Als Nächstes“ nur bei einer tatsächlich chronologischen Auswahl mit erkennbar definiertem Umfang verwenden. Laufende Ausstellungen als eigenen Zustand behandeln. Für den Kalender „Ab heute“ als Standard prüfen; vergangene Termine bleiben über Monatswahl und Archiv erreichbar.

**Abnahme:** Keine veralteten Datumsangaben in Bustour- und Kiez-Tourtexten; Dauer und Zeitspanne widersprechen sich nicht. Tageswechsel und laufende Angebote funktionieren in Berliner Ortszeit. Redaktionelle Auswahl und chronologische Reihenfolge sind erkennbar.

### F04 Aktuelle Ausstellungen und Besuchsinformationen

**P0 · bestätigt · B Ausstellungen.** [Jüdisches Leben und Arbeiten](https://staging.industriesalon.info/ausstellungen/ausstellung-judisches-leben-und-arbeiten-in-schoneweide/) wird auf der Startseite als laufend beworben, fehlt aber im Standardergebnis „Aktuell“ der Ausstellungsübersicht.

Laufzeitquelle und Filterkriterien abgleichen; nicht bloß eine zusätzliche manuelle Karte ergänzen. Die Bereiche „Aktuell im Haus“, „Dauerausstellung“, „Online“ und „Frühere Ausstellungen“ eindeutig organisieren. Den Besuchskasten um bestätigte Öffnungszeiten, Adresse, Eintritt und Zugang ergänzen.

**Abnahme:** Die Ausstellung erscheint während ihrer bestätigten Laufzeit im passenden Bereich und danach im Archiv. Dauer- und Onlineausstellungen bleiben erreichbar. Alle Besuchsangaben stimmen mit den gemeinsamen Besuchsinformationen überein.

### F05 Öffnung und Zugang

**P0 für falsche Besuchsversprechen · Prüfauftrag · A Blocker 6 und 7; B Führungen.** Den aktuellen Zugang zum Behrensbau und die Folgen für betroffene Führungen bestätigen. Den Hinweis datieren beziehungsweise einen überprüfbaren Aktualisierungsstand pflegen und auf die betroffenen Angebote begrenzen.

„Museum heute geschlossen“ von Führungen, Bürozeiten und Sonderöffnungen unterscheiden. Eine Erwähnung des Behrensbaus als historischer Ort ist kein Versprechen einer Innenbesichtigung.

**Abnahme:** Besucher erkennen, ob Museum und Bau zugänglich sind, ob die Führung stattfindet und was sich an ihrer Route ändert. Ein beliebiger Termin überschreibt keine regulären Öffnungszeiten.

### F06 Projektstatus und Rückblickhandlungen

**P1 · bestätigt · B Projekte und Kalender.** KiezKarte erscheint in der Übersicht als kommend, auf der Detailseite als Pilotphase 2026 mit Verkaufsstellen. Bei Anne Rabe führt „Zum Rückblick“ zu einem fehlenden Bericht.

Den Projektstatus mit dem Projektteam abstimmen und in Übersicht und Detail konsistent verwenden. „In Planung“, „Läuft“ und „Abgeschlossen“ sind geeignete öffentliche Bezeichnungen, sofern sie den bestehenden Statusvertrag abbilden. Vergangene Veranstaltungen weiterhin automatisch zeitlich einordnen; „Rückblick lesen“ nur bei vorhandenem Material, sonst beispielsweise „Ankündigung lesen“. Alte externe ZiG-Verweise zugunsten des aktuellen Reihenbezugs prüfen.

**Abnahme:** Kein Projekt erhält allein aus seiner Position einen falschen Status. Berichtlose vergangene Veranstaltungen versprechen keinen Bericht. Vorhandene Berichte und ihre Beziehungen bleiben erhalten.

### F07 Atlas und historische Einordnung

**P1 · Verhalten bestätigt, Produktentscheidung offen · A Blocker 10.** Empfehlung: Die Epoche zunächst als **historische Perspektive** behandeln und irreführende identische Trefferzahlen an den Epochen entfernen oder klar erklären. „Keine gesicherten Angaben für diese Epoche“ bleibt unterscheidbar von „Ort existierte nicht“. Eine echte Filterung wäre eine gesonderte Änderung des bestehenden Vertrags.

Epochenbegriffe zwischen Zeitleiste, Sammlungen und Atlas abgleichen; unterschiedliche Gliederung nur behalten, wenn ihr Zweck verständlich ist. Akteursnamen ausschreiben, Kürzel ergänzend anzeigen. „Industrieakteure“ nicht pauschal durch „Werke“ ersetzen, wenn die Liste auch Personen oder Institutionen enthält. Kioskmodus nur dort anbieten, wo er für öffentliche Besucher einen erklärbaren Nutzen hat.

**Abnahme:** Auswahl, Zähler, Karte und Ergebnisliste vermitteln dieselbe Bedeutung. Suche und weitere Filter bleiben kombinierbar. Fehlende historische Belege werden nicht zu erfundenen Zuordnungen.

### F08 Eingebettete Karten

**P1, bei blockierter Seitennutzung P0 · Codebefund bestätigt · A Blocker 9 und Seitennotizen.** Scrollrad-Zoom der interaktiven Bildkarten so begrenzen, dass normales Seitenscrollen möglich bleibt; vorhandene Zoomschaltflächen erhalten. Marker, zugängliche Bezeichnungen, Karten und Stationsliste auf dieselbe Reihenfolge und geografische Zuordnung prüfen. Für die Elektropolis-Route eine gut erreichbare vollständige Stationsübersicht vorsehen; die genaue Desktopanordnung ist Gestaltung.

**Abnahme:** Maus, Tastatur und Touch ermöglichen Seiten- und Kartennutzung ohne Sackgasse. Jeder Marker führt zum richtigen Ort. Atlas und Bildkarten werden getrennt geprüft.

### F09 Metadaten und Archivobjekte

**P1 · exemplarisch bestätigt · A Archiv; B Sammlungen und Film und Ton.** Das geprüfte [Kinder-im-WF-Objekt](https://staging.industriesalon.info/archivobjekte/kinder-mit-erzieherin-am-sandkasten-vor-dem-wf-gebaude/) zeigt Bild, Titel und einen Satz, aber keine weiterführenden Objektangaben.

Vorhandene Datierung, Urheber, Inventarnummer, Rechte, Herkunft und Serienbezüge über den bestehenden Dateninhaber ausgeben; fehlende Angaben nicht erfinden. Relevante Serien- und Objektbeziehungen sichtbar machen. Bei Videos Aufnahme-, Veröffentlichungs-, Ereignis- und biografische Jahre unterscheiden: „Abriss 2007“ mit unbeschriftetem „1927“ ist zunächst eine mehrdeutige Ausgabe, keine bewiesene falsche Jahresquelle. Anzeigenamen der Archivfilter vereinheitlichen; Motiv und Medienart getrennt behandeln. Keine automatischen Zusammenlegungen von Taxonomiebegriffen.

**Abnahme:** Ein Foto, ein technisches Objekt und ein Video haben verständlich beschriftete Fakten. Vorhandene Rechte- und Quellenangaben sind zugänglich; fehlende optionale Werte erzeugen keine leeren Zeilen. Serienlinks führen zu passendem Material.

### F10 Links und funktionslose Platzhalter

**P1 · teils bestätigt, teils Entscheidung · A Platzhalter; B Fehler 09.** Social-Media-Symbole mit bestätigten Vereinsprofilen verknüpfen oder bis dahin ausblenden. Den generischen Footerlink „Website“ prüfen. „Ihre E-Mail-Adresse“ und eine zugängliche Feldbeschriftung sind klarer als das alleinige Beispiel `name@example.org`. Technische Downloadtitel unter F01/T02 bereinigen.

Rechtliche Sprungmarken auf Ziel, Tastaturbedienung und dauerhafte Erreichbarkeit prüfen. Eigene URLs können die Orientierung verbessern; der Audit allein begründet keine Pflicht zum Umzug. Ältere datierte oder sprachlich unglückliche Slugs nur mit vollständiger Weiterleitungsliste und Aktualisierung interner Links ändern. Über das Twinkl-Badge redaktionell entscheiden.

**Abnahme:** Keine generischen Social-Ziele oder toten Handlungen. Bestehende externe Links und Lesezeichen funktionieren nach jeder freigegebenen URL-Änderung weiter.

### F11 Ladeverhalten und leere Abschnitte

**P1 · Prüfauftrag · A Platzhalter; B beobachtete Ladezustände.** Frühe Dateinamen und später geladene Bilder nicht pauschal als fehlende Medien verbuchen. Bildzustände nach dem Laden und bei einem echten Ladefehler prüfen. Den wiederholt funktionierenden Atlas nicht als dauerhaften Ausfall klassifizieren.

Den leeren zusätzlichen Gruppenabschnitt entfernen, ausblenden oder sinnvoll auf den vorhandenen Gruppenreiter verweisen. Leere verwandte Inhalte ausblenden. Das generische „VIDEO“-Motiv durch ein passendes, freigegebenes Standbild ersetzen.

**Abnahme:** Keine öffentlich sichtbaren technischen Dateinamen als Ersatztext; aussagekräftige Alternativtexte bleiben erhalten. Leere Ergebnisse haben eine passende Rückmeldung. Bilder und Atlas erreichen nach dem Laden ihren vorgesehenen Zustand.

## Texte und Besucherinformationen

Die folgenden Fassungen sind ein gemeinsamer redaktioneller Ausgangspunkt. Konkrete Preise, historische Aussagen, Namen und Zugangsbedingungen werden erst nach fachlicher Bestätigung veröffentlicht. Für institutionelle Seiten ist „Sie“ der vorgeschlagene Standard; bewusst abweichende Projektansprache muss pro Projekt konsistent bleiben.

| Paket | Korrektur und vorgeschlagene Fassung | Abnahme und Quelle |
| --- | --- | --- |
| **T01 P0 — Vollständige Texte** | Startseitensatz nach „Unterstützer de“, abgebrochene Teamtexte und den offenen Sponsorensatz ersetzen. Vorschlag Sponsoreneinstieg: „Wir danken unseren Förderern und Partnern.“ Teamkarten erhalten Name, bestätigte Aufgabe und einen vollständigen kurzen Satz. | Keine Satz- oder Zitatabbrüche; keine erfundenen persönlichen Charakterisierungen. A Platzhalter, Über uns; B Fehler 02 und 08. |
| **T02 P0/P1 — Interne Sprache und Metadaten** | „WEBP / media / image/webp“, „Archive: Theme“, leere Schrägstriche, doppelte „FOTOALBUM“-Angaben und „Gewerbe / Bueros“ am jeweiligen Ursprung korrigieren. Layoutanweisungen wie „Die zwei nächsten Vorhaben stehen …“ und „Projektideen gehören in die Struktur …“ entfernen. „Korpus öffnen“ wird je Ziel etwa „Fotos ansehen“; „Inhaltstyp“ im Kalender „Angebot“, „Dekade“ „Jahrzehnt“. | Weder technische Arbeitsreste noch leere Trenner; Fachbegriffe im eigentlichen Katalog bleiben dort erhalten, wo sie präzise helfen. A Platzhalter und Copy; B Fehler 07 und 10, Besuchersprache. |
| **T03 P1 — Startseite** | Ein Einstieg statt mehrerer Grundsatzerklärungen: **„Schöneweide entdecken“** — „Alte Werkshallen, erstaunliche Technik und die Menschen dahinter. Entdecken Sie Berlins Industriegeschichte im Museum, bei einer Führung und draußen an der Spree.“ „Führung finden · Raum mieten“ als echte Handlungen ausgeben oder die redundante Zeile entfernen. „Zwischen Werkshallen und Spree“ als Überschrift verwenden, falls der Abschnitt bleibt. | Angebot und Hauptaktion im ersten sichtbaren Bereich verständlich. Institutionelle Bezeichnung darf als Zusatz bleiben. A Homepage und Copy; B Startseite. |
| **T04 P1 — Führungen und Kalender** | **„Mit uns durch Schöneweide“** — „Hinter den Backsteinfassaden steckt mehr, als man von der Straße aus sieht. Unsere Führungen erzählen von Fabriken, Erfindungen und den Menschen im Viertel. Wählen Sie einen öffentlichen Termin oder fragen Sie einen Rundgang für Ihre Gruppe an.“ Ermäßigungsregeln in die Preis- und Buchungsinformationen verschieben. Kalender: „Was ist los im Industriesalon?“ Repair-Café mit bestätigten Annahmebedingungen beschreiben; keinen ungeprüften monatlichen Rhythmus ergänzen. | Ziel, Termin, Dauer, Treffpunkt, Preis und Anfrageweg stehen vor langen Regeln. Bustour und Kiez-Tour erhalten vollständige zeitlose Kurztexte. A Führungen; B Führungen und Kalender. |
| **T05 P1 — Sammlungen und Archiv** | **„Was von der Arbeit bleibt“** — „Ein Werkfoto, eine Elektronenröhre, ein persönlicher Bericht: Unsere Sammlung erzählt vom Arbeiten und Leben in Schöneweide. Entdecken Sie die Menschen hinter der Technik und die Geschichten der großen Werke.“ Archiv: **„Im Archiv von Schöneweide stöbern“** mit Suchfeld früh im Seitenverlauf. Konkrete Zugänge zu Menschen, Technik, Werken sowie Fotos und Dokumenten. | Keine wiederholte Erklärung des Beziehungsmodells. Trefferzahlen haben einen erkennbaren Bezug; Rechercheanfrage für nicht online verfügbare Bestände ist erreichbar. A Copy und Archiv; B Sammlungen. |
| **T06 P1 — Ausstellungen** | **„Technik sehen. Menschen kennenlernen.“** — „Entdecken Sie originale Arbeitsplätze, historische Fotos und Geschichten aus Schöneweide. Besuchen Sie unsere Ausstellungen im Industriesalon oder erkunden Sie ausgewählte Themen online.“ Methodische Abschnitte stark kürzen beziehungsweise aus der Besuchsübersicht nehmen. | Was, wo, wann und zu welchen Bedingungen erscheint vor „Konstellationen“ und „Leseschichten“. Keine zweite konkurrierende Filteroberfläche. B Ausstellungen. |
| **T07 P1 — Projekte** | **„Was wir in Schöneweide bewegen“**. Futura: „Das Programm entsteht derzeit. Termine, Orte und Mitwirkende werden noch abgestimmt.“ Bestätigte Angaben bleiben davon getrennt. KiezKarte: „Diese Betriebe sind dabei. Bei jedem Angebot finden Sie den Vorteil und die geltenden Bedingungen.“ ZiG: „Bisherige Abende“ statt Bedienungsanleitung; fehlende Leerzeichen und leere „hier“-Verweise beheben. | Übergabevermerke verschwinden aus öffentlicher Werbesprache; relevante Quellen bleiben als Quellen zugänglich. Kiezfondsanteil, Partnerbedingungen, Preis und Gültigkeit sind bestätigt; keine automatische Verlängerung über 2026. B Projekte. |
| **T08 P1 — Über uns** | Gründung, Sammlung und heutige Tätigkeit konkret erzählen. Doppelte Palast-/Ruinenformeln kürzen. Vorschlag nach Prüfung der Gründungsgeschichte: „2009 gründeten engagierte Schöneweider den Industriesalon, um die Sammlung des früheren Werks für Fernsehelektronik zu bewahren.“ Beim Bundesverdienstkreuz Empfänger und Jahr belegen. | Vollständige Teamtexte mit Zuständigkeiten; Auszeichnungen nachvollziehbar. Die bereits verbesserte Triptychon-Geometrie bleibt erhalten. A Über uns; B Über uns. |
| **T09 P1 — Raum mieten** | **„Feiern und arbeiten im Industriesalon“**. „350 m² Raumhöhe“ durch „Ausstellungsraum mit 350 m² Fläche und rund 7 Metern Höhe“ ersetzen. Zulässige Personenzahlen nach Raum und Bestuhlung, Preisrahmen, Zeiten, Ausstattung und Cateringbedingungen ergänzen. „Wir melden uns mit den Möglichkeiten und einem Angebot.“ | Keine Ableitung der Kapazität aus der Zahl vorhandener Stühle. Bestehende Anfragehandlung gut sichtbar; ein Formular wird nur bei begründetem Zusatznutzen geplant. A Salon; B Raum mieten. |
| **T10 P1 — Publikationen und Film** | **„Schöneweide zum Weiterlesen“** und **„Schöneweide in Film und Ton“**. „Longreads“ gegebenenfalls „Online lesen“. Cover, Autor, Jahr, Inhalt und passende Handlung zeigen. Englische Datumsdarstellung lokalisieren; Arbeitstitel wie „Mueller WF“ fachlich klären. Lokalen Sicherungsstand aus „Röhren für die Republik“ entfernen; eine tatsächlich unvollständige Reihe sachlich als unvollständig kennzeichnen. | Vollständige Kurztexte statt automatisch abgerissener Langtexte. Erscheinungs-, Aufnahme- und Ereignisjahr eindeutig beschriftet. B Publikationen und Film und Ton. |

**Gemeinsame Schreibregeln:** Eine konkrete Aussage pro Teaser; Links benennen das Ziel. Jahreszahlen, Personen und historische Details nicht zur Belebung eines Textes erfinden. Einheitliche Bildunterschriften aus Titel, belegter Datierung und erforderlichem Nachweis. Nicht umbrechende Abstände bei Einheiten wie `7 m` und passenden Zeitangaben; einheitliche Pfeile und Leerzeichen. Claim, Menübezeichnungen und Anrede bewusst vereinheitlichen. Rechtlich relevante Einwilligungstexte nicht allein wegen optischer Wiederholung streichen.

## Bilder und Gestaltung

**Empfohlene Richtung:** vorhandenes Markenrot, warme helle Flächen, große reale Fotos und klare Lesbarkeit. Dunkle Darstellungen dürfen für Sammlung, Film oder Exponate bestehen bleiben, wenn Bedienung und Kontrast konsistent sind. Die bestehende [Gestaltungs- und Rendererordnung](gesture-treatment-consolidation-plan.md) bleibt Grundlage.

| Paket | Konkrete Korrektur | Abnahme |
| --- | --- | --- |
| **V01 P0 — Breiten und Umbruch** | Überlauf der Führungsseite am besitzenden Layout beheben. Hero-Hinweis und Katalog-/Galeriebreiten getrennt untersuchen. Keine globale Abschneideregel als Ersatz für die Ursache. Auch Archivnavigation auf Telefonbreite prüfen. | Bei 390, 768, 1348 und 1720 px kein unbeabsichtigter horizontaler Seitenscroll. Lokale Galerien bleiben bedienbar. A Führungen; B gezielte visuelle Korrekturen; zusätzliche mobile Gegenprüfung. |
| **V02 P0/P1 — Header und Navigation** | Eine gemeinsame Logo-, Menü- und Scrolllogik. Das große fixe Zeichen darf Inhalte und fokussierte Links nicht überdecken. Eine kompakte Variante beim Scrollen ist ein Vorschlag, kein zwingender Neubau. Menü auf hellen und dunklen Hintergründen lesbar halten; redundantes Hamburgerzeichen im offenen Menü prüfen. | Titel, Text, Sprungziele und Tastaturfokus bleiben sichtbar. Logo, Menü, Suche und zentrale Angebote sind konsistent erreichbar. A Blocker 8; B Gestaltung. |
| **V03 P1 — Typografie und Abstände** | Überschriftenrollen und Lesebreiten im bestehenden Tokensystem ordnen. Unbeabsichtigte frühe Umbrüche und übergroße Leerflächen auf Publikationen, Sammlungen und Vermietung reduzieren; Feature/Folgeüberschrift bei Veranstaltungen ausreichend trennen. Fließtext als Arbeitswert etwa 60–75 Zeichen pro Zeile, nicht als starre Vorgabe für jede Komponente. | Lesbare Hierarchie und zusammenhängende Abschnitte auf allen Prüfbreiten. Keine pauschale Ersetzung sämtlicher Überschriftenfonts. A Visual design; B Gestaltung. |
| **V04 P1 — Karten und Handlungen** | Eine verständliche primäre Handlung pro Karte; Titel, vollständiger Kurztext, benötigte Fakten. Leere Bild-/Titelbänder und unnötige verschachtelte Kästen reduzieren. Primäre Aktion, sekundäre Aktion und Textlink klar unterscheiden. Kicker haben innerhalb einer Reihe dieselbe Bedeutung. | Gleiche Interaktion sieht gleich aus; unterschiedliche Inhaltstypen behalten nötige Unterschiede. Bereits ganzflächig verlinkte Besuchskarten unverändert funktionsfähig. Papierkarten bleiben bis zu einer bewussten Designentscheidung. A Karten und Buttons; B Gestaltung. |
| **V05 P1 — Bilder und Kontrast** | Reale Hauptmotive beibehalten. Fotos bewusst beschneiden; Plakate, Logos und Buchcover vollständig zeigen. „VIDEO“-Platzhalter ersetzen; Partnerlogos auf vergleichbare optische Höhe bringen. Text auf Bildern mit passender Position oder ruhiger Fläche sichern; Verdunkelung nicht pauschal erhöhen oder entfernen. | Keine unlesbaren Beschriftungen, verzerrten Motive oder hochgezogenen unscharfen Vorschaubilder. Kontrast an den tatsächlichen Hintergründen messen. Alternativtexte und Rechteangaben erhalten. A Visual design; B Gestaltung. |
| **V06 P1 — Seitenaufbau** | Startseite auf Einstieg, Besuch/Programm, Stadtteil, aktuelle Projekte, Archiv/Mitmachen und Raum/Besuch fokussieren. Wiederholte Erklärabschnitte zusammenführen. Hero-Handlungen enger mit dem Einstieg verbinden und leere Zwischenbänder reduzieren. Besuchskasten von Führungen übersichtlich nach öffentlichen Terminen, Gruppenanfrage und Fakten ordnen. Über-uns-Fließtext nicht mitten im Satz über unabhängige Spalten führen; semantische Überschriftenreihenfolge erhalten. | Die Hauptaufgabe jeder Seite ist sofort erkennbar. Keine bloß dekorativen leeren Spalten. Kein starres „jedes Detail muss oberhalb der Falz liegen“. A Seitennotizen; B Startseite und Layoutfamilien. |
| **V07 P1/P2 — Recherche und Kartenansichten** | Archivsuche früh zeigen, Filter verständlich beschriften und verschachtelte Scrollbereiche auf Nutzbarkeit prüfen. Bildvergrößerung für Objekte über vorhandene Möglichkeiten vorsehen. Kartenstile nach Orientierungszweck beurteilen; nicht historische Bildkarte und interaktiven Atlas technisch gleichsetzen. Karussells auf eindeutige, tastaturfähige Bedienung prüfen. | Suche, Filter, Bildansicht und Karten funktionieren mit Maus, Tastatur und Touch. Native Scrollbarkeit nicht allein deshalb entfernen, weil Pfeile vorhanden sind. A Archiv, Karten und Karussells; B Sammlungen. |
| **V08 P1/P2 — Footer und kleine Elemente** | Footerüberschriften ausrichten, Newsletterfeld am Text ausrichten, Linkbezeichnungen vereinheitlichen. Doppelmarker aus Strich und Punkt sowie konkurrierende Trennlinien im bestehenden Gestaltungssystem prüfen. „Startseite“ im Footer, Twinkl-Badge und zusätzliche Kategorieakzente sind redaktionelle Entscheidungen. | Sichtbare Ordnung ohne Verlust sinnvoller Navigation, Bedeutungen oder notwendiger Formulardaten. A Menü/Footer und Visual design; B gemeinsame Gestaltung. |

Vier Layoutfamilien sind als Entwurfsprüfung hilfreich: **Besuchsangebot**, **Geschichte/Ort**, **Recherche**, **Projekt**. Daraus entstehen nicht automatisch vier neue technische Templates. Zunächst je ein vorhandenes Beispiel verbessern und die gemeinsamen Regeln daran überprüfen.

## Benötigte fachliche Entscheidungen

| Verantwortlicher Bereich | Zu bestätigen | Betroffene Pakete |
| --- | --- | --- |
| Programm und Führungen | Verfügbarkeitsquelle, öffentliche und Gruppenpreise, tatsächliche Dauer, Treffpunkte, Behrensbauzugang, Repair-Café-Annahmebedingungen und Rhythmus | F02–F05, T04 |
| Ausstellungen | Laufzeiten, Besuchsbedingungen, Eintritt und Zuordnung der aktuellen Ausstellung | F04, T06 |
| Projektteams | KiezKarte-Status, Fondsanteil, aktuelle Partnerbedingungen, Preis/Gültigkeit; Futura bestätigt versus geplant; vorhandenes Rückblickmaterial | F06, T07 |
| Verein und Redaktion | Gründungsgeschichte, Teamaufgaben, Auszeichnungen, Social-Profile, Fördererbezeichnung, einheitlicher Claim und Anrede | F10, T01, T08 |
| Raumverantwortliche | Zulässige Belegung je Nutzung, Preisrahmen, Verfügbarkeit, Catering und Ausstattung | T09 |
| Sammlung und Archiv | Umfang der veröffentlichten Bestände, Datierungsarten, Rechte, Serienbezüge, Anzeigenamen und Epochenkonzept | F07, F09, T05, T10 |
| Gestaltung und Redaktion | Papierkarten, Typografierollen, Kategorieakzente, Headerverhalten, Umfang der Startseite, Kioskzugang | V02–V08, F07 |

Die Audits ersetzen keine dieser Sachentscheidungen. Bereits bekannte korrekte Angaben müssen dafür nicht erneut infrage gestellt werden.

## Technische Umsetzung und Datentransfer

Die vorhandenen Zuständigkeiten nutzen: Redaktion und `iss-editorial` für gespeicherte Dokumente, `iss-content` für Inhaltsverträge, `iss-occurrences` für Termine und Verfügbarkeit, `iss-frontend` für bestehende Programm-/Kartenfunktionen, `iss-graph` für Beziehungen und Projektionen, das Theme für öffentliche Darstellung.

Vor jeder Umsetzung die tatsächliche Quelle feststellen: gespeichertes JSON, Beitrag, Gutenberg-Block, Theme-Template oder bewusstes Datenbank-Template. Inhalt nicht durch CSS verstecken, wenn die Veröffentlichung oder Zuordnung falsch ist. Keine direkten Reparaturen an Projektionstabellen und kein zweites Status-, Karten- oder Buchungssystem.

Für spätere Releases gilt die bestehende [Git-Austauschfolge](../runbooks/git-exchange.md). Benötigte Inhalts- und Mediendaten werden gezielt über die zuständigen APIs beziehungsweise den privaten Transfer synchronisiert. Vorher Sicherung und Zielzustand prüfen; keine vollständige Datenbanküberschreibung und keine Zuordnung allein nach identischen numerischen IDs.

**GitHub erhält Code und Dokumentation. SQL-Dateien, Dumps, Inhalts-Transferpakete und Medien bleiben außerhalb von Git und werden bei Bedarf privat nach Staging übertragen.** Das reine Plandokument benötigt keine Migration; die inzwischen lokal umgesetzten Inhaltskorrekturen benötigen die oben genannten privaten API-Eingaben.

## Abnahme

1. **Öffentliche Inhalte:** Kein TXL-Reservierungsvermerk, kein technischer Downloadtitel, kein „Archive: Theme“, keine abgebrochene Biografie und keine leeren Metadatentrenner an den erfassten Stellen. Herkunft, Rechte und KI-Kennzeichnung bleiben dort erhalten, wo sie relevant sind.
2. **Programm:** Gleiches Angebot und gleicher Termin liefern auf Startseite, Kalender, Übersicht, Detail und verwandten Karten dieselben bestätigten Daten. Freie, ausgebuchte, abgesagte, unbekannte, vergangene und nur auf Anfrage verfügbare Angebote sind unterscheidbar.
3. **Besuch und Projekte:** Laufende Ausstellung auffindbar; Öffnung und Zugang eindeutig; Projektstatus konsistent; Rückblicklinks versprechen nur vorhandenes Material.
4. **Gestaltung:** Desktop und Mobil bei 390, 768, 1348 und 1720 px prüfen; zusätzlich 200 Prozent Zoom und Tastaturnavigation. Keine globale Überbreite, verdeckten Fokusziele oder blockierenden Karten. About-Triptychon, Projekttitel und Futura-Logo bleiben korrekt.
5. **Medien und Recherche:** Laden und Fehlerfall prüfen. Suchergebnisse, Filterkombinationen, leere Treffer und Objektvergrößerung bedienen. Tatsächliche Bildrechte und Kontext der verwendeten Kinderbilder prüfen; keine pauschale Entfernung allein aufgrund des Motivs.
6. **Formulare und Buchung:** Newsletter, Raumanfrage und öffentliche beziehungsweise Gruppenbuchung separat bis zum überprüfbaren Ergebnis testen. Dafür vorher Testdaten und einen Weg ohne echte Nachrichten oder Buchungen festlegen; reale Sendungen benötigen ausdrückliche Freigabe.
7. **Release:** Änderungen nach der [Prüfmatrix](../agent/verification.md) kontrollieren, erforderliche Inhalte/Medien privat mitführen und auf Staging erneut prüfen. `noindex` des Stagings erhalten. HTTP 200 oder ein erfolgreicher Codecheck allein gilt nicht als gestalterische oder fachliche Abnahme.

**Empfohlener erster Umsetzungsschritt:** F01, T01 und der technische Teil von T02 — öffentliche Reservierungsnotiz, falscher Publikationsblock, technische Downloadausgabe und belegte Satzabbrüche. Parallel dazu lediglich die benötigten Sachangaben für F02–F05 zusammentragen. Anschließend Termin- und Buchungsdarstellung sowie den bestätigten Seitenüberlauf in jeweils eigenen, überprüfbaren Änderungen bearbeiten.
