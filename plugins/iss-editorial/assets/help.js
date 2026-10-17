/* Shared workspace help. Section vocabulary stays in the format registry. */
(function () {
  var create = function (tag, cls, text) { var el = document.createElement(tag); el.className = cls || ''; if (text) { el.textContent = text; } return el; };
  function button(label, action) { var el = create('button', 'iss-editorial-studio__button', label); el.type = 'button'; el.addEventListener('click', action); return el; }

  window.issEditorialHelp = function (root, options) {
    var publishing = options.publishing || {};
    var published = publishing.status === 'publish';
    var nativePublish = document.getElementById('publish');
    var updateLabel = nativePublish && (nativePublish.value || nativePublish.textContent.trim()) || 'Aktualisieren';
    var scheduled = publishing.status === 'future';
    var privatePost = publishing.status === 'private';
    var saveEffect = published
      ? 'Dieses Projekt ist veröffentlicht. „' + updateLabel + '“ übernimmt deine Änderungen auf die öffentliche Seite.'
      : scheduled ? 'Dieser Inhalt ist zur Veröffentlichung geplant. „Aktualisieren“ ändert die geplante Fassung; prüfe den Veröffentlichungstermin in WordPress.'
        : privatePost ? 'Dieser Inhalt ist privat. „Aktualisieren“ ändert die private Fassung. Prüfe die Sichtbarkeit in WordPress, bevor du sie änderst.'
          : publishing.canPublish === false ? 'Du kannst Entwürfe speichern und „Zur Überprüfung einreichen“. Neue Projekte veröffentlicht eine Person mit Veröffentlichungsrechten.'
            : 'Mit „Entwurf speichern“ bleibt der Inhalt ein Entwurf. „Veröffentlichen“ macht ihn entsprechend der gewählten Sichtbarkeit und dem Termin verfügbar.';
    var topics = {
      start: { title: 'Erste Schritte', short: 'Abschnitt auswählen, Inhalt bearbeiten, Vorschau prüfen und bewusst speichern.', paragraphs: [
        'Die Seite besteht aus Abschnitten. Wähle rechts unter „Abschnitte“ einen Eintrag aus. Unter „Inhalt“ bearbeitest du die Texte; „Darstellung“, „Links“ und „Medien“ erscheinen, wenn der Abschnitt diese Möglichkeiten bietet.',
        'Beginne mit einer kleinen Textänderung. Warte auf „Entwurf gesichert“. Mit „Fertig“ kehrst du zur Übersicht zurück. Prüfe anschließend die Vorschau für Desktop und Telefon.',
        'Automatische Sicherungen sind dein privater Arbeitsstand. Erst die WordPress-Schaltflächen speichern die gemeinsame Fassung bzw. aktualisieren die öffentliche Seite. Lies dazu „Speichern und Freigeben“.',
        'Das „?“ im Kopf der Seitenleiste erklärt den aktuellen Bereich. Die vollständige Hilfe erreichst du unter „Weitere Werkzeuge“ → „Hilfe“. Die Hilfe ersetzt vorübergehend die Vorschau. Du kannst daneben weiterarbeiten. „Hilfe schließen“ schließt die Hilfe; deine Eingaben bleiben erhalten.'
      ] },
      save: { title: 'Speichern und Freigeben', short: saveEffect, paragraphs: [
        '„Entwurf gesichert“ bestätigt die automatische Sicherung deines privaten Arbeitsstands. „Noch nicht gesichert“ oder eine Fehlermeldung bedeutet: Lass den Tab offen und prüfe die Meldung.',
        '„Fertig“ sichert den aktuellen Arbeitsstand und führt zur Abschnittsübersicht. Dadurch wird die öffentliche Seite noch nicht geändert.',
        'Verkleinere die Arbeitsfläche, um die normalen WordPress-Speicheraktionen zu erreichen. Welche Aktionen dort verfügbar sind, hängt vom Status und deinen Berechtigungen ab.',
        saveEffect,
        'Bei englischer WordPress-Oberfläche heißen die Aktionen „Save Draft“, „Submit for Review“, „Update“ und „Publish“.',
        'Prüfe vor dem abschließenden Speichern Text, Bilder, Links und Telefon-Vorschau. Warte danach, bis WordPress die Speicherung bestätigt. Projektstatus und WordPress-Veröffentlichungsstatus sind unterschiedliche Angaben.'
      ] },
      text: { title: 'Texte bearbeiten', short: 'Titel und Text im Abschnitt bearbeiten. Die Werkzeugleiste formatiert markierten Text.', paragraphs: [
        'Der Titel benennt den Abschnitt. „Vorspann & Einleitung (optional)“ ergänzt eine kurze Einordnung. Ein Vorspann ist eine kurze Zeile vor der Überschrift; die Einleitung führt in den Text ein.',
        'Markiere Text, bevor du Fett, Kursiv oder einen Link anwendest. Nutze Absätze und Listen für die Gliederung. Prüfe nach dem Einfügen aus Word oder anderen Seiten die Formatierung und Links.',
        'Du kannst unterstützte Textfelder per Doppelklick oder mit Enter auch direkt in der Vorschau bearbeiten. „Fertig“ übernimmt diese Eingabe. Escape verwirft nur die laufende direkte Texteingabe und stellt deren vorherigen Wert wieder her.',
        'Textfarbe, Hervorhebung, Link entfernen und Formatierung entfernen findest du unter „⋯“ in der Textwerkzeugleiste. Das Textfeld wächst mit deinem Text; scrolle in der Seitenleiste.',
        'Die Felder rechts bleiben der vollständige Bearbeitungsweg. Öffne dort „Inhalt“, wenn ein Element in der Vorschau nicht direkt bearbeitet werden kann.'
      ] },
      display: { title: 'Darstellung wählen', short: 'Ordnet vorhandene Inhalte anders an. Prüfe die Wirkung anschließend auf Desktop und Telefon.', paragraphs: [
        'Unter „Darstellung“ wählst du eine vorbereitete Anordnung für diesen Abschnitt. Die kleinen Skizzen und Hinweise erklären die Unterschiede. Inhalte bleiben beim Wechsel erhalten; manche Varianten zeigen andere Teile davon.',
        'Text-Bild-Reihen können beispielsweise als „Themenspalten“ oder „Programm / Chronik“ erscheinen. Bei Fließtext kannst du die Position von Titel und Erzählung wechseln.',
        'Bildanpassung und Ausrichtung verändern den sichtbaren Ausschnitt bzw. die Anordnung. Prüfe, ob wichtige Bildteile sichtbar und Texte lesbar bleiben.',
        'Unter „Seite“ gelten Einstellungen für die gesamte Seite. Eine Seitengestaltung kann mehrere Abschnitte beeinflussen.'
      ] },
      media: { title: 'Bilder auswählen', short: '„Medien suchen“ öffnet die Mediathek. Eine entfernte Bildreferenz löscht nicht die Mediendatei.', paragraphs: [
        'Öffne bei einem Abschnitt mit Bildern den Reiter „Medien“ und wähle „Medien suchen“. In Bild-Text-Paaren verwendest du „Bild wählen“ direkt beim Eintrag.',
        'Wähle ein vorhandenes Bild oder lade eine Datei hoch. Bestätige die Auswahl im Medienfenster. Zum Ersetzen entfernst du das bisherige Bild aus der Auswahl und wählst das neue; prüfe die ausgewählten Miniaturen.',
        'Ergänze eine passende Bildunterschrift. Prüfe den Alternativtext in der Mediathek und beschreibe dort die relevante Bildinformation. Änderungen an Mediendaten können auch andere Verwendungen desselben Bildes betreffen.',
        '„Entfernen“ im Abschnitt löst nur die Zuordnung. Die Datei bleibt in der Mediathek. Die Set-Auswahl hat eigene Berechtigungen; für den Projekt-UAT verwendest du die normale Mediathek.'
      ] },
      links: { title: 'Links und Sprungziele', short: 'Verwende verständliche Linktexte und ein vollständiges Ziel. Prüfe Links in der Vorschau.', paragraphs: [
        'Unter „Links“ ergänzt du eine Beschriftung und das Ziel. Schreibe zum Beispiel „Programm ansehen“ statt „Hier klicken“. Externe Adressen beginnen mit https://.',
        'Im Text markierst du die Wörter und verwendest das Link-Werkzeug. Ein unvollständiger Link kann als privater Entwurf gesichert werden, verhindert aber eine gültige Vorschau oder Veröffentlichung.',
        'Ein „Anker“ ist der kurze Name eines Sprungziels innerhalb der Seite, beispielsweise programm. Verwende keine Leerzeichen und vergib jeden Namen nur einmal. Bereits verlinkte Anker nur nach Prüfung ändern.'
      ] },
      arrange: { title: 'Abschnitte hinzufügen und ordnen', short: 'Neue Abschnitte über „Hinzufügen“ wählen. Am Griff ziehen oder mit den Pfeiltasten verschieben.', paragraphs: [
        'Unter „Hinzufügen“ suchst du einen Abschnittstyp. Der Klick fügt ihn ein; danach füllst du seine Felder. Das Plus in der Vorschau fügt an der dort angezeigten Position ein.',
        'Unter „Abschnitte“ ziehst du einen Eintrag an die gewünschte Stelle. Mit der Tastatur: den Verschiebegriff fokussieren und Pfeil nach oben oder unten drücken.',
        'Über „Abschnitt … von …“ springst du direkt zu einem Abschnitt. Die Pfeile daneben wählen den vorherigen oder nächsten Abschnitt aus. Die Reihenfolge bleibt dabei gleich.',
        'Für einen Seitenauftakt mit großem Bild muss der Auftakt an erster Stelle stehen. Prüfe nach dem Verschieben den Übergang zum nächsten Abschnitt.'
      ] },
      trash: { title: 'Entfernen und Wiederherstellen', short: 'Ganze Abschnitte bleiben im Papierkorb und können wiederhergestellt werden.', paragraphs: [
        '„In Papierkorb“ entfernt den ausgewählten Abschnitt aus dem Seitenaufbau. „Rückgängig“ holt die zuletzt entfernte Fassung zurück.',
        'Du findest entfernte Abschnitte auch unter „Abschnitte“ im „Papierkorb“. Mit „Wiederherstellen“ nimmst du sie wieder auf. Prüfe anschließend die Position.',
        'Das gilt für ganze Abschnitte. Einzelne Fakten, Links oder Bild-Text-Paare haben keinen eigenen Papierkorb. Prüfe daher genau, welchen „Entfernen“-Knopf du verwendest.',
        'Bereits gespeicherte frühere Fassungen sind, sofern vorhanden, unter „Weitere Werkzeuge“ → „Verlauf“ erreichbar. Die Wiederherstellung dort betrifft eine gespeicherte Fassung des gesamten Inhalts.'
      ] },
      preview: { title: 'Vorschau prüfen', short: 'Desktop, Tablet und Telefon ändern nur die Vorschau-Breite. Die Seite wird dadurch nicht veröffentlicht.', paragraphs: [
        'Die Vorschau zeigt deinen Arbeitsstand. „Vorschau aktuell“ bestätigt die angezeigte Fassung. Während einer Aktualisierung oder bei einem Fehler kann noch die vorherige Fassung sichtbar sein.',
        'Mit Desktop, Tablet und Telefon prüfst du verschiedene Breiten. Kontrolliere Überschriften, Bildausschnitte, Textlängen, Links und aufklappbare Inhalte.',
        '„Vorschau aktualisieren“ sichert den Arbeitsstand und fordert eine neue Vorschau an. Diese Aktion veröffentlicht nichts.'
      ] },
      recovery: { title: 'Wenn etwas nicht weitergeht', short: 'Fehlermeldung lesen, ungesicherte Texte erhalten und die angebotene Wiederherstellung bewusst wählen.', paragraphs: [
        '„Entwurf fortsetzen“: Vergleiche den angebotenen Arbeitsstand mit der gespeicherten Fassung. „Entwurf weiterbearbeiten“ übernimmt ihn. Verwirf einen Entwurf nur, wenn du dessen Änderungen nicht mehr brauchst.',
        'Bei ungültigen Angaben: Folge der Meldung zum betroffenen Feld, ergänze zum Beispiel die fehlende Link-Adresse und prüfe die Vorschau erneut. Ein gesicherter Entwurf kann trotzdem noch ungültig für die Veröffentlichung sein.',
        'Bei einer Bearbeitungssperre arbeitet möglicherweise eine andere Person am Inhalt. Stimme dich mit ihr ab. Übernimm den Inhalt nicht, solange sie noch Änderungen bearbeitet.',
        'Nach einem Kontowechsel kann ein alter Tab nicht weiter unter dem neuen Konto speichern. Sichere ungesicherte Texte durch Kopieren und öffne den Editor über den angebotenen Link neu.',
        'Bei einem Netzwerk- oder Speicherfehler: Lass den Tab offen, kopiere ungesicherte Texte und versuche die Sicherung nach Wiederherstellung der Verbindung erneut. Lade nicht einfach neu, solange „Noch nicht gesichert“ angezeigt wird.'
      ] },
      page: { title: 'Seiteneinstellungen und weitere Angaben', short: '„Seite“ betrifft das ganze Projekt. Weitere Angaben erreichst du über „Angaben & Beziehungen …“.', paragraphs: [
        'Unter „Seite“ findest du die verfügbaren Einstellungen zur Seitengestaltung, bei Projekten auch zur Lesenavigation. Prüfe danach die gesamte Vorschau.',
        '„Angaben & Beziehungen …“ verkleinert die Arbeitsfläche und führt zu den zusätzlichen WordPress-Feldern. Dort stehen je nach Inhalt und Berechtigung zum Beispiel Projektstatus und Beziehungen.',
        'Mit „Arbeitsfläche vergrößern“ kehrst du zur großen Bearbeitungsansicht zurück. Vergrößern und Verkleinern speichern oder veröffentlichen nichts.'
      ] }
    };
    if (options.format !== 'projekt') { topics.save.paragraphs = topics.save.paragraphs.map(function (p) { return p.replace(/Projektstatus und WordPress-Veröffentlichungsstatus sind unterschiedliche Angaben\./, ''); }); saveEffect = saveEffect.replace('Dieses Projekt', 'Dieser Inhalt').replace('Neue Projekte', 'Neue Inhalte'); topics.save.short = saveEffect; topics.save.paragraphs[3] = saveEffect; }
    if (options.format === 'projekt') {
      topics.futura = { title: 'Futura: Übungsweg', short: 'Sechs Aufgaben zum Kennenlernen des Projekteditors.', paragraphs: [
        'Futura enthält Bild & Text, Fließtext, Merkpunkte, Text-Bild-Reihen, Überschrift & Intro und Kapitel. Die Hilfe zu einem Abschnitt öffnest du über das „?“ im Kopf der Seitenleiste.',
        'Übe Änderungen, die du nicht veröffentlichen möchtest, in einem neuen Testprojekt. Auf einer veröffentlichten Futura-Seite verändert „Aktualisieren“ die öffentliche Fassung.',
        '1. Öffne einen Textabschnitt und ändere eine Überschrift. Prüfe den Status „Entwurf gesichert“.',
        '2. Wähle für „Bild & Text“ ein Bild aus der Mediathek und ergänze eine Bildunterschrift.',
        '3. Probiere bei einer Text-Bild-Reihe „Themenspalten“ und „Programm / Chronik“. Futura-Beispiele sind „Wo fangen wir an?“ und „Vom ersten Gespräch bis zur langen Nacht.“',
        '4. Verschiebe einen Abschnitt mit dem Griff oder der Tastatur. Prüfe die neue Reihenfolge.',
        '5. Lege einen Abschnitt in den Papierkorb und stelle ihn wieder her.',
        '6. Prüfe Desktop und Telefon. Erkläre vor dem Speichern, was „Fertig“ und was die anschließende WordPress-Aktion bewirken.',
        'Rückmeldung: Nenne Abschnitt, Schaltfläche, erwartetes Ergebnis und tatsächliches Ergebnis. Ergänze bei Darstellungsproblemen Browser und Bildschirmgröße.'
      ] };
    }
    Object.keys(options.sections).forEach(function (type) {
      var section = options.sections[type];
      topics['section:' + type] = { title: section.label, short: (section.help || [])[0] || section.description, paragraphs: [section.description].concat(section.help || []) };
    });
    var panel = create('section', 'iss-editorial-help'); panel.hidden = true; panel.setAttribute('aria-label', 'Hilfe zum Editor'); panel.id = 'iss-editorial-help';
    var head = create('div', 'iss-editorial-help__head');
    var heading = create('h2', '', 'Hilfe zum Editor');
    var close = button('Hilfe schließen', function () { hide(); });
    head.append(heading, close);
    var search = create('input'); search.type = 'search'; search.placeholder = 'Hilfe durchsuchen'; search.setAttribute('aria-label', 'Hilfe durchsuchen');
    var nav = create('nav', 'iss-editorial-help__topics'); nav.setAttribute('aria-label', 'Hilfethemen');
    var article = create('article', 'iss-editorial-help__article');
    var articleTitle = create('h3'); articleTitle.tabIndex = -1;
    var copy = create('div'); article.append(articleTitle, copy);
    var opener = null;
    var activeKey = 'start';
    var trigger = button('Hilfe', function () { if (panel.hidden) { show('start', trigger); } else { hide(); } });
    trigger.classList.add('iss-editorial-help-trigger');
    trigger.setAttribute('aria-controls', panel.id); trigger.setAttribute('aria-expanded', 'false');
    panel.append(head, search, nav, article); options.preview.appendChild(panel); options.tools.appendChild(trigger);
    function list() {
      nav.replaceChildren();
      var query = search.value.trim().toLocaleLowerCase('de');
      Object.keys(topics).forEach(function (key) {
        var topic = topics[key];
        if ((topic.title + ' ' + topic.paragraphs.join(' ')).toLocaleLowerCase('de').indexOf(query) < 0) { return; }
        var item = button(topic.title, function () { read(key, true); }); item.setAttribute('aria-current', String(activeKey === key)); nav.appendChild(item);
      });
      if (!nav.children.length) { nav.appendChild(create('p', '', 'Keine passende Hilfe. Versuche zum Beispiel „Bild“, „Entwurf“ oder „Darstellung“.')); }
    }
    function read(key, focus) {
      var topic = topics[key] || topics.start; activeKey = topics[key] ? key : 'start';
      articleTitle.textContent = topic.title; copy.replaceChildren();
      topic.paragraphs.filter(Boolean).forEach(function (text) { copy.appendChild(create('p', '', text)); });
      if (key.indexOf('section:') === 0) {
        var section = options.sections[key.slice(8)];
        (section.treatments || []).forEach(function (choice) { copy.appendChild(create('p', '', choice.label + (choice.hint ? ': ' + choice.hint : ''))); });
      }
      list(); if (focus) { articleTitle.focus(); article.scrollIntoView({ block: 'nearest' }); }
    }
    function show(key, from) {
      var origin = from || document.activeElement; hidePopup();
      options.afterEditing(function () { opener = origin; panel.hidden = false; root.classList.add('iss-editorial-studio--help'); trigger.setAttribute('aria-expanded', 'true'); search.value = ''; read(key, true); });
    }
    function hide() { panel.hidden = true; root.classList.remove('iss-editorial-studio--help'); trigger.setAttribute('aria-expanded', 'false'); if (opener && opener.isConnected) { opener.focus(); } else { trigger.focus(); } }
    search.addEventListener('input', list);
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); var first = nav.querySelector('button'); if (first) { first.click(); } }
    });
    panel.addEventListener('keydown', function (event) { if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); hide(); } });

    // Native popovers use the top layer, so inspector scrolling cannot clip help.
    var popup = create('div', 'iss-editorial-help-popup'); popup.id = 'iss-editorial-help-popup'; popup.setAttribute('popover', 'manual'); popup.hidden = true;
    var geometry = create('style'); root.append(popup, geometry);
    var target = null; var previousDescription = ''; var timer = null; var pinned = false;
    function hidePopup() {
      window.clearTimeout(timer);
      if (target) { if (previousDescription) { target.setAttribute('aria-describedby', previousDescription); } else { target.removeAttribute('aria-describedby'); } if (target.dataset.helpOpen) { target.setAttribute('aria-expanded', 'false'); } }
      if (!popup.hidden) { popup.hidePopover(); } popup.hidden = true; target = null; pinned = false;
    }
    function placePopup() {
      if (!target) { return; }
      var box = target.getBoundingClientRect(); var width = Math.min(320, window.innerWidth - 24);
      var left = Math.max(12, Math.min(box.left, window.innerWidth - width - 12));
      var height = popup.getBoundingClientRect().height;
      var top = box.bottom + 6; if (top + height > window.innerHeight - 12) { top = Math.max(12, box.top - height - 6); }
      geometry.textContent = '#iss-editorial-help-popup{left:' + left + 'px;top:' + top + 'px;width:' + width + 'px}';
    }
    function explain(el, pin) {
      if (!el.isConnected || (target === el && pinned === pin)) { return; } hidePopup();
      var key = el.dataset.help; var topic = topics[key]; if (!topic) { return; }
      target = el; pinned = pin; previousDescription = el.getAttribute('aria-describedby') || '';
      popup.replaceChildren(create('p', '', el.dataset.helpText || topic.short));
      popup.setAttribute('role', pin ? 'dialog' : 'tooltip');
      if (pin) {
        popup.setAttribute('aria-label', 'Hilfe: ' + topic.title);
        popup.append(button('Mehr dazu', function () { var from = target; show(key, from); }), button('Schließen', function () { var from = target; hidePopup(); if (from && from.isConnected) { from.focus(); hidePopup(); } }));
        el.setAttribute('aria-expanded', 'true');
      } else { popup.removeAttribute('aria-label'); }
      el.setAttribute('aria-describedby', (previousDescription + ' ' + popup.id).trim());
      popup.hidden = false; popup.showPopover(); placePopup();
      if (pin) { popup.querySelector('button').focus(); }
    }
    function hint(el, key, text) { el.dataset.help = key; if (text) { el.dataset.helpText = text; } el.removeAttribute('title'); return el; }
    function helpButton(key, label) {
      var el = button(label || '?', function () { explain(el, true); }); el.classList.add('iss-editorial-help-button'); el.dataset.helpOpen = 'true';
      el.setAttribute('aria-label', 'Hilfe: ' + (topics[key] || topics.start).title); el.setAttribute('aria-haspopup', 'dialog'); el.setAttribute('aria-expanded', 'false'); return hint(el, key);
    }
    function leaving() { window.clearTimeout(timer); timer = window.setTimeout(function () { if (!pinned && (!target || document.activeElement !== target) && !popup.matches(':hover')) { hidePopup(); } }, 180); }
    function over(event) {
      var el = event.target.closest('[data-help]'); if (!el || pinned || (event.pointerType && event.pointerType !== 'mouse')) { return; }
      window.clearTimeout(timer); timer = window.setTimeout(function () { explain(el, false); }, 350);
    }
    function focus(event) { var el = event.target.closest('[data-help]'); if (el && !pinned) { explain(el, false); } }
    function escape(event) { if (event.key === 'Escape' && target) { var from = target; var returnFocus = pinned; event.preventDefault(); event.stopPropagation(); hidePopup(); if (returnFocus && from.isConnected) { from.focus(); hidePopup(); } } }
    function outside(event) { if (target && !popup.contains(event.target) && !target.contains(event.target)) { hidePopup(); } }
    root.addEventListener('pointerover', over); root.addEventListener('pointerout', leaving); root.addEventListener('focusin', focus); root.addEventListener('focusout', leaving);
    popup.addEventListener('pointerenter', function () { window.clearTimeout(timer); }); popup.addEventListener('pointerleave', leaving);
    root.addEventListener('keydown', escape, true); document.addEventListener('pointerdown', outside); window.addEventListener('resize', hidePopup); root.addEventListener('scroll', hidePopup, true);
    return {
      button: helpButton, hint: hint, show: show,
      saveNotice: saveEffect + ' Automatische Sicherungen und „Fertig“ bleiben privat.',
      context: function (key, el) { hidePopup(); el.dataset.help = topics[key] ? key : 'arrange'; el.setAttribute('aria-label', 'Hilfe: ' + (topics[key] || topics.arrange).title); },
      destroy: function () { hidePopup(); document.removeEventListener('pointerdown', outside); window.removeEventListener('resize', hidePopup); }
    };
  };
})();
