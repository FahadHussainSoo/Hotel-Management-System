/*!
 * polish.js — Phase 6 UI polish layer (public site)
 * Purely additive progressive enhancement: page-load spinner, scroll
 * reveal animations, responsive table wrapping, and a light page-enter
 * fade. Does not touch any existing scripts.js / main.js behaviour.
 */
(function () {
  "use strict";

  function ready(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
  }

  // ---------------------------------------------------------------
  // Page loader: shown by an inline snippet as early as possible in
  // <body>, hidden here once the page has finished loading (or after
  // a short fallback delay so a slow subresource can never strand it).
  // ---------------------------------------------------------------
  function hideLoader() {
    var loader = document.getElementById("page-loader");
    if (loader) loader.classList.add("is-hidden");
  }
  window.addEventListener("load", hideLoader);
  setTimeout(hideLoader, 2500); // fallback safety net

  // ---------------------------------------------------------------
  // Page-enter fade for the main content area.
  // ---------------------------------------------------------------
  ready(function () {
    document.documentElement.classList.add("fade-in");
  });

  // ---------------------------------------------------------------
  // Scroll-reveal: fade/slide in cards and section headers as they
  // enter the viewport. Falls back to doing nothing (content stays
  // fully visible) if IntersectionObserver isn't supported.
  // ---------------------------------------------------------------
  ready(function () {
    if (!("IntersectionObserver" in window)) return;

    var targets = document.querySelectorAll(
      ".item-rooms, .portfolio-box, .page-section .card, .team-member, .gallery-item"
    );
    if (!targets.length) return;

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("js-revealed");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" }
    );

    targets.forEach(function (el) {
      el.classList.add("js-reveal-ready");
      observer.observe(el);
    });
  });

  // ---------------------------------------------------------------
  // Auto-wrap any Bootstrap .table that isn't already inside a
  // .table-responsive container, so wide tables scroll horizontally
  // on small screens instead of breaking the layout.
  // ---------------------------------------------------------------
  ready(function () {
    document.querySelectorAll("table.table").forEach(function (table) {
      if (table.closest(".table-responsive, .polish-table-scroll")) return;
      var wrapper = document.createElement("div");
      wrapper.className = "polish-table-scroll";
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });
  });

  // ---------------------------------------------------------------
  // Smooth-scroll fallback for any anchor link that isn't already
  // handled by scripts.js's js-scroll-trigger binding.
  // ---------------------------------------------------------------
  ready(function () {
    document.querySelectorAll('a[href*="#"]:not(.js-scroll-trigger)').forEach(function (link) {
      var url;
      try {
        url = new URL(link.href, window.location.href);
      } catch (e) {
        return;
      }
      if (url.pathname !== window.location.pathname || !url.hash || url.hash === "#") return;
      link.addEventListener("click", function (e) {
        var target = document.querySelector(url.hash);
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    });
  });
})();
