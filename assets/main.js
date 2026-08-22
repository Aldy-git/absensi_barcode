/**
 * AbsensiBarcode - Main UI Scripts
 * Universal sidebar toggle & responsive drawer handler
 */

(function () {
  'use strict';

  window.toggleSidebar = function (e) {
    if (e && e.stopPropagation) e.stopPropagation();
    if (window.innerWidth <= 1000) {
      if (document.body.classList.contains('sidebar-open')) {
        window.closeMobileSidebar(e);
      } else {
        window.openMobileSidebar(e);
      }
    } else {
      document.body.classList.toggle('sidebar-collapsed');
      setTimeout(function () {
        window.dispatchEvent(new Event('resize'));
      }, 300);
    }
  };

  // Backwards compatibility aliases
  window.toggleMobileSidebar = window.toggleSidebar;

  window.openMobileSidebar = function (e) {
    if (e && e.stopPropagation) e.stopPropagation();
    document.body.classList.add('sidebar-open');
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      sidebar.classList.add('show');
      sidebar.classList.add('active');
    }
    const backdrop = document.getElementById('sidebarBackdrop') || document.querySelector('.sidebar-backdrop');
    if (backdrop) {
      backdrop.classList.add('show');
    }
  };

  window.closeMobileSidebar = function (e) {
    if (e && e.stopPropagation) e.stopPropagation();
    document.body.classList.remove('sidebar-open');
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      sidebar.classList.remove('show');
      sidebar.classList.remove('active');
    }
    const backdrop = document.getElementById('sidebarBackdrop') || document.querySelector('.sidebar-backdrop');
    if (backdrop) {
      backdrop.classList.remove('show');
    }
  };

  function setupSidebarHandlers() {
    let backdrop = document.getElementById('sidebarBackdrop') || document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.className = 'sidebar-backdrop';
      backdrop.id = 'sidebarBackdrop';
      document.body.appendChild(backdrop);
    }

    backdrop.onclick = function (e) {
      window.closeMobileSidebar(e);
    };

    const toggleBtns = document.querySelectorAll('#sidebarToggleBtn, .sidebar-toggle-btn, .mobile-toggle-btn, [data-toggle="sidebar"]');
    toggleBtns.forEach(function (btn) {
      btn.onclick = function (e) {
        window.toggleSidebar(e);
      };
    });

    const closeBtns = document.querySelectorAll('#sidebarCloseBtn, .sidebar-close-btn, [data-close="sidebar"]');
    closeBtns.forEach(function (btn) {
      btn.onclick = function (e) {
        window.closeMobileSidebar(e);
      };
    });

    const navLinks = document.querySelectorAll('.sidebar nav a');
    navLinks.forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 1000) {
          window.closeMobileSidebar();
        }
      });
    });
  }

  // Global document click delegation as safety net
  document.addEventListener('click', function (e) {
    const toggleBtn = e.target.closest('#sidebarToggleBtn, .sidebar-toggle-btn, .mobile-toggle-btn, [data-toggle="sidebar"]');
    if (toggleBtn) {
      window.toggleSidebar(e);
      return;
    }

    const closeBtn = e.target.closest('#sidebarCloseBtn, .sidebar-close-btn, [data-close="sidebar"]');
    if (closeBtn) {
      window.closeMobileSidebar(e);
      return;
    }

    const backdrop = e.target.closest('#sidebarBackdrop, .sidebar-backdrop');
    if (backdrop && document.body.classList.contains('sidebar-open')) {
      window.closeMobileSidebar(e);
      return;
    }
  });

  // Close on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      window.closeMobileSidebar(e);
    }
  });

  // Auto close if window is resized wider than 1000px
  window.addEventListener('resize', function () {
    if (window.innerWidth > 1000 && document.body.classList.contains('sidebar-open')) {
      window.closeMobileSidebar();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupSidebarHandlers);
  } else {
    setupSidebarHandlers();
  }
})();
