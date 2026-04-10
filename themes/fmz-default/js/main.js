/**
 * TekBB Theme Engine
 * Color mode controlled by theme settings (admin-only)
 */
var TekBB = (function ($) {
  "use strict";

  /* ── Theme logic ── */

  function getTheme() {
    // Get theme setting from data attribute set by admin
    var themeMode = document.documentElement.getAttribute('data-theme-mode') || 'light';
    return themeMode;
  }

  function applyTheme(t) {
    document.documentElement.setAttribute("data-theme", t);
    document.documentElement.setAttribute("data-bs-theme", t);

    var nav = document.querySelector(".navbar");
    if (nav) {
      nav.classList.remove("navbar-light", "bg-white", "navbar-dark", "bg-dark");
      if (t === "dark") {
        nav.classList.add("navbar-dark", "bg-dark");
      } else {
        nav.classList.add("navbar-light", "bg-white");
      }
    }

    // SCEditor iframe dark mode
    var iframes = document.querySelectorAll(".sceditor-container iframe");
    for (var j = 0; j < iframes.length; j++) {
      try {
        var doc = iframes[j].contentDocument || iframes[j].contentWindow.document;
        doc.body.style.backgroundColor = t === "dark" ? "#0f172a" : "#fff";
        doc.body.style.color = t === "dark" ? "#e2e8f0" : "#1e293b";
      } catch (e) {}
    }
  }

  /* ── UI helpers ── */

  function initScrollTop() {
    var $btn = $(".top_button");
    $(window).on("scroll", function () {
      $btn.toggleClass("visible", $(this).scrollTop() > 300);
    });
  }

  function initExpCol() {
    // Init collapsed state from content visibility
    $(".expcolimage").each(function () {
      var $el = $(this);
      var controls = $el.attr("data-controls");
      if (!controls) {
        var img = $el.find("img.expander");
        if (img.length && img.attr("id")) {
          controls = img.attr("id").replace("_img", "");
          $el.attr("data-controls", controls);
        }
      }
      if (controls) {
        var $content = $("#" + controls + "_e");
        if ($content.length && $content.is(":hidden")) {
          $el.attr("data-collapsed", "1");
        }
      }
    });

    // Click handler — toggle content + icon
    $(document).on("click", ".expcolimage", function (e) {
      e.stopPropagation();
      var $el = $(this);
      var controls = $el.attr("data-controls");
      if (!controls) {
        var img = $el.find("img.expander");
        if (img.length && img.attr("id")) {
          controls = img.attr("id").replace("_img", "");
        }
      }
      if (!controls) return;

      var $content = $("#" + controls + "_e");
      if ($content.length) {
        var wasHidden = $content.is(":hidden");
        $content.slideToggle("fast");
        $el.attr("data-collapsed", wasHidden ? "0" : "1");

        // Save collapsed state cookie (MyBB format)
        if (typeof Cookie !== "undefined") {
          var saved = [], newCol = [];
          var cur = Cookie.get("collapsed");
          if (cur) {
            saved = cur.split("|");
            $.each(saved, function (i, v) {
              if (v !== controls && v !== "") newCol.push(v);
            });
          }
          if (!wasHidden) newCol.push(controls);
          Cookie.set("collapsed", newCol.join("|"));
        }
      }
    });
  }

  /* ── Boot ── */

  applyTheme(getTheme());

  $(function () {
    applyTheme(getTheme());
    initScrollTop();
    initExpCol();
  });

  return { getTheme: getTheme };
})(jQuery);