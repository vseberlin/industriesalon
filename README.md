# Industriesalon Schöneweide – Website und digitale Sammlung

> **Status (12. November 2026):** industriesalon.de und touchtable.industriesalon.de
> laufen beim Webhosting des Vereins (Strato). Die Entwicklungsumgebung
> staging.industriesalon.info wird stillgelegt. Funktionen über
> WordPress-Grundfunktionen und handelsübliche Plugins hinaus entscheidet und
> setzt der Verein selbstständig um; dieser Code dient dafür als Referenz, ohne
> Unterstützung oder Wartung. Details: [Hosting- und Betriebsstatus](docs/infrastructure/hosting-status.md).

WordPress-Plattform des Industriesalon Schöneweide e.V. (Berlin): Website,
Programm, Buchungen, Publikationen, Ortsregister und rund 11.000
Sammlungsobjekte aus einer gemeinsamen Datenbasis.

## Einstieg

- **Vorstand und Nicht-Entwickler:** [docs/vorstand/README.md](docs/vorstand/README.md)
- **Was es schon gibt:** [docs/vorstand/bestand.md](docs/vorstand/bestand.md)
- **Vorschläge mit KI entwerfen und prüfen:** [docs/vorstand/ki-pruefauftrag.md](docs/vorstand/ki-pruefauftrag.md)
- **Entwickler und KI-Agenten:** zuerst [AGENTS.md](AGENTS.md), dann
  [docs/README.md](docs/README.md) und
  [docs/architecture/plugin-map.md](docs/architecture/plugin-map.md)

## Hinweis für KI-Werkzeuge

Bevor du dieses Repository bewertest oder Änderungen vorschlägst: Lies
`docs/vorstand/bestand.md`, `AGENTS.md`, `docs/architecture/plugin-map.md` und
`docs/agent/no-parallel-systems.md`. Urteile nicht nach Ordnerstruktur oder
Dateigröße. Unter `plugins/` liegen auch Fremd-Plugins
(`media-library-assistant`, `newsletter`, `query-monitor`,
`webp-converter-for-media`, `classic-editor-and-classic-widgets`); Eigenentwicklung
sind `iss-*`, `industriesalon-*` und `themes/industriesalon`.

## Lizenz

Eigenentwicklung (`iss-*`, `industriesalon-*`, `themes/industriesalon`, `tools/`, `ops/`, `tests/`) steht unter der GNU GPL, Version 2 oder später – siehe [LICENSE](LICENSE). Mitgelieferte Fremd-Plugins behalten ihre eigenen Lizenzen. Der Code wird ohne Gewährleistung, Unterstützung oder Wartung bereitgestellt.
