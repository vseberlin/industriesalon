# Hosting- und Betriebsstatus

Stand: 12. November 2026. Dieses Dokument ist die verbindliche Quelle zum
Betriebsstatus. Abweichende Angaben in älteren Dokumenten beschreiben einen
früheren Stand.

## Betrieb durch den Verein

- **industriesalon.de** ist die öffentliche Website des Industriesalon
  Schöneweide e. V. Sie läuft beim Webhosting des Vereins (Strato) und wird vom
  Verein betrieben.
- **touchtable.industriesalon.de** läuft seit dem 12. November 2026 ebenfalls
  bei Strato; der DNS-A-Eintrag wurde umgestellt.

## Staging wird stillgelegt

- **staging.industriesalon.info** und der zugehörige Hetzner-Server sind eine
  private Entwicklungsumgebung. Sie werden stillgelegt und nicht an den Verein
  übergeben.
- Staging ist kein Ziel für Deployments, Datenabgleiche oder Wiederherstellungen
  mehr. Alle Staging-Abläufe in Runbooks, Skills und Plänen sind historisch.
- Der Staging-Stand weicht strukturell von industriesalon.de ab und ist nicht
  aktuell. Weiterführende Arbeit setzt auf industriesalon.de auf.

## Funktionsumfang und Zuständigkeit

- Der Verein betreibt seine Website mit WordPress-Grundfunktionen und
  handelsüblichen Plugins.
- Funktionen darüber hinaus entscheidet und setzt der Verein selbstständig um.
  Das betrifft insbesondere Sammlung und Archiv, Ortsregister und Atlas,
  Verknüpfungen, Suche, Buchung mit Gutscheinen und redaktionelle Gestaltungsbausteine.
- Die Eigenentwicklung in diesem Repository (`iss-*`, `industriesalon-*`,
  `themes/industriesalon`) steht unter der GPL ([LICENSE](../../LICENSE)) und kann
  dafür als Referenz oder Grundlage dienen. Sie wird ohne Unterstützung oder
  Wartung bereitgestellt.
- Dieses Repository bleibt im Konto des Autors. Der Verein übernimmt den Code bei
  Bedarf per GitHub-Import in ein eigenes Konto.
