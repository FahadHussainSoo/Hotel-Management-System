/*!
 * admin/polish.js — Phase 6 UI polish layer (admin dashboard)
 * Adds the mobile sidebar toggle behaviour and wraps any un-wrapped
 * table in a horizontally-scrollable container. Purely additive.
 */
(function () {
  "use strict";

  function ready(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
  }

  ready(function () {
    var sidebar = document.querySelector(".admin-sidebar");
    var toggle = document.querySelector(".admin-sidebar-toggle");
    var backdrop = document.querySelector(".admin-sidebar-backdrop");
    if (sidebar && toggle && backdrop) {
      function closeSidebar() {
        sidebar.classList.remove("is-open");
        backdrop.classList.remove("is-visible");
      }
      toggle.addEventListener("click", function () {
        sidebar.classList.toggle("is-open");
        backdrop.classList.toggle("is-visible");
      });
      backdrop.addEventListener("click", closeSidebar);
      sidebar.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", closeSidebar);
      });
    }

    document.querySelectorAll("table.table").forEach(function (table) {
      if (table.closest(".table-responsive, .polish-table-scroll")) return;
      var wrapper = document.createElement("div");
      wrapper.className = "polish-table-scroll";
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });
  });
})();
