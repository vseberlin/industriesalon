(function () {
  function getSingleVideoFrame() {
    return document.querySelector("[data-video-single-frame]");
  }

  function seekVideo(frame, seconds) {
    if (!frame || !frame.contentWindow || !Number.isFinite(seconds)) {
      return false;
    }

    frame.contentWindow.postMessage(
      JSON.stringify({
        event: "command",
        func: "seekTo",
        args: [Math.max(0, seconds), true],
      }),
      "*"
    );
    frame.contentWindow.postMessage(
      JSON.stringify({
        event: "command",
        func: "playVideo",
        args: [],
      }),
      "*"
    );

    return true;
  }

  document.addEventListener("click", function (event) {
    var link = event.target.closest("[data-video-seek]");
    if (!link) {
      return;
    }

    var seconds = Number.parseInt(link.getAttribute("data-video-seek") || "", 10);
    var frame = getSingleVideoFrame();
    if (!seekVideo(frame, seconds)) {
      return;
    }

    var targetId = (link.getAttribute("href") || "").replace(/^#/, "");
    var target = targetId ? document.getElementById(targetId) : null;
    if (target) {
      target.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  });
})();
