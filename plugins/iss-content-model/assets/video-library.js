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

  function getPlayer(root) {
    return root ? root.querySelector("[data-video-player]") : null;
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

    var frame = root.querySelector("[data-video-player-frame]");
    var title = root.querySelector("[data-video-player-title]");
    var text = root.querySelector("[data-video-player-text]");
    var kicker = root.querySelector("[data-video-player-kicker]");
    var year = root.querySelector("[data-video-player-year]");
    var duration = root.querySelector("[data-video-player-duration]");
    var yearWrap = root.querySelector("[data-video-player-year-wrap]");
    var durationWrap = root.querySelector("[data-video-player-duration-wrap]");
    var link = root.querySelector("[data-video-player-link]");
    var transcriptLink = root.querySelector("[data-video-player-transcript]");
    var transcriptWrap = root.querySelector("[data-video-player-transcript-wrap]");
    var returnWrap = root.querySelector("[data-video-player-return-wrap]");

    var embedUrl = (trigger.getAttribute("data-video-embed") || "").trim();
    var titleText = trigger.getAttribute("data-video-title") || "";
    var bodyText = trigger.getAttribute("data-video-text") || "";
    var yearText = trigger.getAttribute("data-video-year") || "";
    var durationText = trigger.getAttribute("data-video-duration") || "";
    var categoryText = trigger.getAttribute("data-video-categories") || "";
    var videoUrl = trigger.getAttribute("data-video-url") || "";
    var permalink = trigger.getAttribute("data-video-permalink") || "";
    var hasTranscript = (trigger.getAttribute("data-video-has-transcript") || "").trim() === "1";
    var videoId = trigger.getAttribute("data-video-id") || "";

    if (frame && embedUrl) {
      var joiner = embedUrl.indexOf("?") === -1 ? "?" : "&";
      frame.src = embedUrl + joiner + "autoplay=1";
      frame.title = titleText;
    }

    setText(title, titleText);
    setText(text, bodyText);
    setText(kicker, categoryText);
    setText(year, yearText);
    setText(duration, durationText);
    setWrapVisibility(yearWrap, yearText);
    setWrapVisibility(durationWrap, durationText);

    if (link) {
      link.href = videoUrl;
    }

    if (transcriptLink) {
      transcriptLink.href = permalink ? permalink.replace(/\/?$/, "/") + "#transkript" : "#transkript";
    }
    setWrapVisibility(transcriptWrap, hasTranscript ? "1" : "");
    setWrapVisibility(returnWrap, "1");

    activateCard(root, videoId);
  }

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
    player.scrollIntoView({ behavior: "smooth", block: "nearest" });
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
    });
  });
})();
