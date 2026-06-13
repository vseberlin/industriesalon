(function () {
  function getLibraryRoot(node) {
    if (!node || !node.closest) {
      return null;
    }

    return node.closest("[data-video-library-root], .iss-video-library--interactive");
  }

  function setText(node, value) {
    if (!node) {
      return;
    }

    var text = (value || "").trim();
    node.textContent = text;

    if (node.hasAttribute("hidden")) {
      node.hidden = text === "";
    }
  }

  function setWrapVisibility(node, value) {
    if (!node) {
      return;
    }

    node.hidden = (value || "").trim() === "";
  }

  function setHtml(node, html) {
    if (!node) {
      return;
    }

    node.innerHTML = html || "";
  }

  function setImage(node, src, alt) {
    if (!node) {
      return;
    }

    var imageSrc = (src || "").trim();
    if (!imageSrc) {
      node.hidden = true;
      node.removeAttribute("src");
      node.alt = "";
      return;
    }

    node.hidden = false;
    node.src = imageSrc;
    node.alt = alt || "";
  }

  function buildEmbedUrl(embedUrl, autoplay) {
    if (!embedUrl) {
      return "";
    }

    var isYoutube = /youtube(?:-nocookie)?\.com/i.test(embedUrl);
    var params = [];
    if (autoplay) {
      params.push("autoplay=1");
    }
    if (isYoutube) {
      params.push("rel=0", "playsinline=1", "iv_load_policy=3");
    }

    if (!params.length) {
      return embedUrl;
    }

    return embedUrl + (embedUrl.indexOf("?") === -1 ? "?" : "&") + params.join("&");
  }

  function showFrame(root, embedUrl, titleText, autoplay) {
    if (!root) {
      return;
    }

    var poster = root.querySelector("[data-video-player-poster]");
    var frameWrap = root.querySelector("[data-video-player-frame-wrap]");
    var frame = root.querySelector("[data-video-player-frame]");

    if (!frame || !frameWrap || !embedUrl) {
      return;
    }

    var nextSrc = buildEmbedUrl(embedUrl, autoplay);
    if (frame.src !== nextSrc) {
      frame.src = nextSrc;
    }
    frame.title = titleText || "";
    frame.hidden = false;
    frameWrap.hidden = false;

    if (poster) {
      poster.hidden = true;
    }
  }

  function getPlayer(root) {
    return root ? root.querySelector("[data-video-player]") : null;
  }

  function setActiveTab(root, tabName) {
    if (!root || !tabName) {
      return;
    }

    root.querySelectorAll("[data-video-player-tab]").forEach(function (button) {
      var isActive = button.getAttribute("data-video-player-tab") === tabName;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    });

    root.querySelectorAll("[data-video-player-panel]").forEach(function (panel) {
      var isActive = panel.getAttribute("data-video-player-panel") === tabName;
      panel.classList.toggle("is-active", isActive);
      panel.hidden = !isActive;
    });
  }

  function updatePanelContent(root, trigger) {
    if (!root || !trigger) {
      return;
    }

    var card = trigger.closest(".iss-video-card");
    var template = card ? card.querySelector(".iss-video-card__panel-template") : null;
    if (!template || !template.content) {
      return;
    }

    ["transcript", "chapters"].forEach(function (name) {
      var source = template.content.querySelector('[data-video-panel-source="' + name + '"]');
      var target = root.querySelector('[data-video-player-panel-target="' + name + '"]');
      if (!target) {
        return;
      }

      setHtml(target, source ? source.innerHTML : "");
    });
  }

  function activateCard(root, videoId) {
    var cards = root.querySelectorAll(".iss-video-card");
    cards.forEach(function (card) {
      card.classList.toggle("is-active", card.getAttribute("data-video-id") === videoId);
    });
  }

  function updatePlayer(root, trigger) {
    if (!root || !trigger) {
      return;
    }

    var title = root.querySelector("[data-video-player-title]");
    var text = root.querySelector("[data-video-player-text]");
    var kicker = root.querySelector("[data-video-player-kicker]");
    var originalDate = root.querySelector("[data-video-player-original-date]");
    var source = root.querySelector("[data-video-player-source]");
    var language = root.querySelector("[data-video-player-language]");
    var year = root.querySelector("[data-video-player-year]");
    var duration = root.querySelector("[data-video-player-duration]");
    var posterImage = root.querySelector("[data-video-player-poster-image]");
    var posterFallback = root.querySelector("[data-video-player-poster-fallback]");
    var posterTitle = root.querySelector("[data-video-player-poster-title]");
    var originalDateWrap = root.querySelector("[data-video-player-original-date-wrap]");
    var yearWrap = root.querySelector("[data-video-player-year-wrap]");
    var durationWrap = root.querySelector("[data-video-player-duration-wrap]");
    var languageWrap = root.querySelector("[data-video-player-language-wrap]");
    var link = root.querySelector("[data-video-player-link]");
    var permalink = root.querySelector("[data-video-player-permalink]");
    var transcriptText = root.querySelector("[data-video-player-transcript-text]");
    var transcriptWrap = root.querySelector("[data-video-player-transcript-wrap]");
    var returnWrap = root.querySelector("[data-video-player-return-wrap]");

    var embedUrl = (trigger.getAttribute("data-video-embed") || "").trim();
    var titleText = trigger.getAttribute("data-video-title") || "";
    var bodyText = trigger.getAttribute("data-video-text") || "";
    var originalDateText = trigger.getAttribute("data-video-original-date") || "";
    var yearText = trigger.getAttribute("data-video-year") || "";
    var durationText = trigger.getAttribute("data-video-duration") || "";
    var sourceText = trigger.getAttribute("data-video-source-label") || "";
    var languageText = trigger.getAttribute("data-video-language") || "";
    var categoryText = trigger.getAttribute("data-video-categories") || "";
    var thumbnailUrl = trigger.getAttribute("data-video-thumbnail") || "";
    var videoUrl = trigger.getAttribute("data-video-url") || "";
    var permalinkUrl = trigger.getAttribute("data-video-permalink") || "";
    var hasTranscript = (trigger.getAttribute("data-video-has-transcript") || "").trim() === "1";
    var transcriptLabel = trigger.getAttribute("data-video-transcript-link-label") || "";
    var videoId = trigger.getAttribute("data-video-id") || "";

    setText(title, titleText);
    setText(text, bodyText);
    setText(kicker, categoryText);
    setText(originalDate, originalDateText);
    setText(source, sourceText);
    setText(language, languageText);
    setText(year, yearText);
    setText(duration, durationText);
    setImage(posterImage, thumbnailUrl, titleText);
    setText(posterTitle, titleText);

    if (posterFallback) {
      posterFallback.hidden = (thumbnailUrl || "").trim() !== "";
    }

    setWrapVisibility(originalDateWrap, originalDateText);
    setWrapVisibility(yearWrap, yearText);
    setWrapVisibility(durationWrap, durationText);
    setWrapVisibility(languageWrap, languageText);

    if (link) {
      link.href = videoUrl;
    }

    if (permalink) {
      permalink.href = permalinkUrl;
    }

    setText(transcriptText, transcriptLabel);
    setWrapVisibility(transcriptWrap, hasTranscript ? "1" : "");
    setWrapVisibility(returnWrap, "");

    root.setAttribute("data-active-video-embed", embedUrl);
    root.setAttribute("data-active-video-title", titleText);
    updatePanelContent(root, trigger);
    showFrame(root, embedUrl, titleText, true);
    activateCard(getLibraryRoot(trigger) || root, videoId);
  }

  document.addEventListener("click", function (event) {
    var filter = event.target.closest(".iss-video-library__filter");
    if (!filter) {
      return;
    }

    var libraryRoot = getLibraryRoot(filter);
    if (!libraryRoot) {
      return;
    }

    var targetId = (filter.getAttribute("href") || "").replace(/^#/, "");
    var target = targetId ? document.getElementById(targetId) : null;
    if (!target) {
      return;
    }

    event.preventDefault();
    libraryRoot.querySelectorAll(".iss-video-library__filter").forEach(function (item) {
      item.classList.toggle("is-active", item === filter);
    });
    target.scrollIntoView({ behavior: "smooth", block: "start" });
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, "", "#" + targetId);
    }
  });

  document.addEventListener("click", function (event) {
    var trigger = event.target.closest(".iss-video-card__trigger, .iss-video-playlist__lead-trigger");
    if (!trigger) {
      return;
    }

    var libraryRoot = getLibraryRoot(trigger);
    if (!libraryRoot) {
      return;
    }

    var player = getPlayer(libraryRoot);
    if (!player) {
      return;
    }

    event.preventDefault();
    player.__lastTrigger = trigger;
    updatePlayer(player, trigger);
  });

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-video-player-play]");
    if (!button) {
      return;
    }

    var libraryRoot = getLibraryRoot(button);
    var player = getPlayer(libraryRoot);
    if (!player) {
      return;
    }

    event.preventDefault();
    showFrame(
      player,
      (player.getAttribute("data-active-video-embed") || "").trim(),
      player.getAttribute("data-active-video-title") || "",
      true
    );
  });

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-video-player-tab], [data-video-player-open-tab]");
    if (!button) {
      return;
    }

    var libraryRoot = getLibraryRoot(button);
    var player = getPlayer(libraryRoot);
    if (!player) {
      return;
    }

    event.preventDefault();
    setActiveTab(
      player,
      button.getAttribute("data-video-player-open-tab") || button.getAttribute("data-video-player-tab") || "overview"
    );
  });

  document.addEventListener("click", function (event) {
    var button = event.target.closest("[data-video-player-return]");
    if (!button) {
      return;
    }

    var libraryRoot = getLibraryRoot(button);
    var player = getPlayer(libraryRoot);
    var trigger = player && player.__lastTrigger ? player.__lastTrigger : null;
    if (!trigger) {
      return;
    }

    event.preventDefault();
    trigger.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(function () {
      trigger.focus({ preventScroll: true });
    }, 220);
  });

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-video-library-root], .iss-video-library--interactive").forEach(function (libraryRoot) {
      var player = getPlayer(libraryRoot);
      if (!player) {
        return;
      }

      var defaultId = player.getAttribute("data-default-video-id");
      if (!defaultId) {
        return;
      }

      activateCard(libraryRoot, defaultId);
      setActiveTab(player, "overview");
    });
  });
})();
