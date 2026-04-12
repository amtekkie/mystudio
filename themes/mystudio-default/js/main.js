/**
 * TekBB Theme Engine
 * Dark mode only — no theme switching
 */
var TekBB = (function ($) {
  "use strict";

  /* ── Theme init ── */

  function applyTheme() {
    document.documentElement.setAttribute("data-theme", "dark");
    document.documentElement.setAttribute("data-bs-theme", "dark");

    // SCEditor iframe dark mode
    var iframes = document.querySelectorAll(".sceditor-container iframe");
    for (var j = 0; j < iframes.length; j++) {
      try {
        var doc = iframes[j].contentDocument || iframes[j].contentWindow.document;
        doc.body.style.backgroundColor = "#0f172a";
        doc.body.style.color = "#e2e8f0";
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

  applyTheme();

  function initClickableRows() {
    $(document).on('click', '.forumbit-row[data-href]', function (e) {
      if ($(e.target).closest('a, button, input, select, label, .expcolimage, .star_rating, [data-ms-user-modal]').length) return;
      window.location.href = $(this).data('href');
    });
  }

  $(function () {
    applyTheme();
    initScrollTop();
    initExpCol();
    initClickableRows();
  });

  return {};
})(jQuery);