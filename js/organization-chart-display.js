/**
 * @file
 * JavaScript for organization chart display functionality.
 */

(function ($, Drupal, drupalSettings) {
  "use strict";

  /**
   * Organization Chart Display behavior.
   */
  Drupal.behaviors.organizationChartDisplay = {
    attach: function (context, settings) {
      $(".organization-chart", context)
        .once("org-chart-display")
        .each(function () {
          new OrganizationChart(this);
        });
    },
  };

  /**
   * Organization Chart class.
   */
  function OrganizationChart(element) {
    this.element = $(element);
    this.chartId = this.element.data("chart-id");
    this.config = this.element.data("chart-config") || {};
    this.viewport = this.element.find(".org-chart-viewport");
    this.canvas = this.element.find(".org-chart-canvas");
    this.popup = this.element.find(".org-chart-popup");

    this.zoom = 1;
    this.panX = 0;
    this.panY = 0;
    this.isDragging = false;
    this.isFullscreen = false;

    this.init();
  }

  OrganizationChart.prototype = {
    init: function () {
      this.bindEvents();
      this.drawLines();
      this.setupScrollbars();

      // Enable lazy loading if configured
      if (this.config.lazy_loading !== false) {
        this.setupLazyLoading();
      }

      // Setup animations if enabled
      if (this.config.animation_enabled !== false) {
        this.setupAnimations();
      }

      // Auto-fit chart if needed
      setTimeout(() => {
        this.fitToView();
      }, 100);
    },

    bindEvents: function () {
      const self = this;

      // Zoom controls
      this.element.find(".org-chart-zoom-in").on("click", function () {
        self.zoomIn();
      });

      this.element.find(".org-chart-zoom-out").on("click", function () {
        self.zoomOut();
      });

      this.element.find(".org-chart-zoom-reset").on("click", function () {
        self.resetZoom();
      });

      // Fullscreen toggle
      this.element.find(".org-chart-fullscreen").on("click", function () {
        self.toggleFullscreen();
      });

      // Element popups
      this.element
        .find(".org-chart-element-popup-trigger")
        .on("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.showPopup($(this));
        });

      // Element clicks for links
      this.element
        .find('.org-chart-element[role="button"]')
        .on("click", function () {
          const linkUrl = $(this).find(".org-chart-element-link").attr("href");
          if (linkUrl) {
            window.open(linkUrl, "_blank", "noopener,noreferrer");
          }
        });

      // Keyboard navigation
      this.element
        .find('.org-chart-element[role="button"]')
        .on("keydown", function (e) {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            $(this).trigger("click");
          }
        });

      // Popup controls
      this.popup
        .find(".org-chart-popup-close, .org-chart-popup-backdrop")
        .on("click", function () {
          self.hidePopup();
        });

      // Mouse wheel zoom
      this.viewport.on("wheel", function (e) {
        if (e.ctrlKey || e.metaKey) {
          e.preventDefault();
          const delta = e.originalEvent.deltaY;
          if (delta < 0) {
            self.zoomIn(0.1);
          } else {
            self.zoomOut(0.1);
          }
        }
      });

      // Pan with middle mouse button
      this.viewport.on("mousedown", function (e) {
        if (e.which === 2) {
          // Middle mouse button
          e.preventDefault();
          self.startPan(e);
        }
      });

      // Touch support for mobile
      this.setupTouchEvents();

      // Window resize
      $(window).on("resize", function () {
        self.handleResize();
      });

      // Keyboard shortcuts
      $(document).on("keydown", function (e) {
        if (
          self.element.is(":visible") &&
          !$(e.target).is("input, textarea, select")
        ) {
          self.handleKeyboard(e);
        }
      });
    },

    setupTouchEvents: function () {
      const self = this;
      let lastTouchDistance = 0;
      let lastTouchCenter = { x: 0, y: 0 };

      this.viewport.on("touchstart", function (e) {
        if (e.originalEvent.touches.length === 2) {
          const touch1 = e.originalEvent.touches[0];
          const touch2 = e.originalEvent.touches[1];

          lastTouchDistance = Math.hypot(
            touch2.clientX - touch1.clientX,
            touch2.clientY - touch1.clientY
          );

          lastTouchCenter = {
            x: (touch1.clientX + touch2.clientX) / 2,
            y: (touch1.clientY + touch2.clientY) / 2,
          };
        }
      });

      this.viewport.on("touchmove", function (e) {
        if (e.originalEvent.touches.length === 2) {
          e.preventDefault();

          const touch1 = e.originalEvent.touches[0];
          const touch2 = e.originalEvent.touches[1];

          const distance = Math.hypot(
            touch2.clientX - touch1.clientX,
            touch2.clientY - touch1.clientY
          );

          const center = {
            x: (touch1.clientX + touch2.clientX) / 2,
            y: (touch1.clientY + touch2.clientY) / 2,
          };

          if (lastTouchDistance > 0) {
            const scale = distance / lastTouchDistance;
            self.zoom *= scale;
            self.zoom = Math.max(0.1, Math.min(3, self.zoom));
            self.updateTransform();
          }

          lastTouchDistance = distance;
          lastTouchCenter = center;
        }
      });
    },

    handleKeyboard: function (e) {
      switch (e.key) {
        case "+":
        case "=":
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.zoomIn();
          }
          break;
        case "-":
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.zoomOut();
          }
          break;
        case "0":
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.resetZoom();
          }
          break;
        case "f":
        case "F11":
          if (!e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            this.toggleFullscreen();
          }
          break;
        case "Escape":
          if (this.isFullscreen) {
            this.toggleFullscreen();
          }
          this.hidePopup();
          break;
      }
    },

    zoomIn: function (factor = 0.2) {
      this.zoom = Math.min(3, this.zoom + factor);
      this.updateTransform();
    },

    zoomOut: function (factor = 0.2) {
      this.zoom = Math.max(0.1, this.zoom - factor);
      this.updateTransform();
    },

    resetZoom: function () {
      this.zoom = 1;
      this.panX = 0;
      this.panY = 0;
      this.updateTransform();
    },

    fitToView: function () {
      const viewportWidth = this.viewport.width();
      const viewportHeight = this.viewport.height();
      const canvasWidth = this.canvas[0].scrollWidth;
      const canvasHeight = this.canvas[0].scrollHeight;

      const scaleX = viewportWidth / canvasWidth;
      const scaleY = viewportHeight / canvasHeight;

      this.zoom = Math.min(scaleX, scaleY, 1) * 0.9; // 90% to add some padding
      this.panX = 0;
      this.panY = 0;
      this.updateTransform();
    },

    updateTransform: function () {
      this.canvas.css(
        "transform",
        `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`
      );

      // Update zoom display
      const zoomPercent = Math.round(this.zoom * 100);
      this.element.find(".org-chart-zoom-level").text(`Zoom: ${zoomPercent}%`);
    },

    startPan: function (e) {
      const self = this;
      this.isDragging = true;

      const startX = e.clientX - this.panX;
      const startY = e.clientY - this.panY;

      $(document).on("mousemove.orgchart", function (e) {
        self.panX = e.clientX - startX;
        self.panY = e.clientY - startY;
        self.updateTransform();
      });

      $(document).on("mouseup.orgchart", function () {
        self.isDragging = false;
        $(document).off(".orgchart");
      });
    },

    toggleFullscreen: function () {
      if (this.isFullscreen) {
        this.element.removeClass("org-chart-fullscreen");
        $("body").removeClass("org-chart-fullscreen-active");
        this.isFullscreen = false;
      } else {
        this.element.addClass("org-chart-fullscreen");
        $("body").addClass("org-chart-fullscreen-active");
        this.isFullscreen = true;
      }

      setTimeout(() => {
        this.handleResize();
      }, 100);
    },

    showPopup: function (trigger) {
      const title = trigger.data("element-title") || "";
      const description = trigger.data("element-description") || "";
      const imageUrl = trigger.data("element-image") || "";
      const linkUrl = trigger.data("element-link") || "";

      this.popup.find(".org-chart-popup-title").text(title);
      this.popup.find(".org-chart-popup-description").html(description);

      if (imageUrl) {
        this.popup
          .find(".org-chart-popup-image")
          .html(`<img src="${imageUrl}" alt="${title}">`);
      } else {
        this.popup.find(".org-chart-popup-image").empty();
      }

      if (linkUrl) {
        this.popup
          .find(".org-chart-popup-link")
          .html(
            `<a href="${linkUrl}" target="_blank" rel="noopener noreferrer">View Profile</a>`
          );
      } else {
        this.popup.find(".org-chart-popup-link").empty();
      }

      this.popup.attr("aria-hidden", "false");

      // Focus management
      this.popup.find(".org-chart-popup-close").focus();
    },

    hidePopup: function () {
      this.popup.attr("aria-hidden", "true");
    },

    drawLines: function () {
      const svg = this.element.find(".org-chart-lines");
      if (svg.length === 0) return;

      const elements = this.element.find(".org-chart-element-wrapper");

      elements.each(function () {
        const element = $(this);
        const elementId = element.data("element-id");
        const parentId = element.find(".org-chart-element").data("parent-id");

        if (parentId) {
          // Find parent element
          const parent = elements.filter(`[data-element-id="${parentId}"]`);
          if (parent.length > 0) {
            // Calculate line coordinates
            const parentRect = parent[0].getBoundingClientRect();
            const elementRect = element[0].getBoundingClientRect();
            const svgRect = svg[0].getBoundingClientRect();

            const x1 = parentRect.left + parentRect.width / 2 - svgRect.left;
            const y1 = parentRect.bottom - svgRect.top;
            const x2 = elementRect.left + elementRect.width / 2 - svgRect.left;
            const y2 = elementRect.top - svgRect.top;

            // Update line position
            svg
              .find(`[data-from="${parentId}"][data-to="${elementId}"]`)
              .attr("x1", x1)
              .attr("y1", y1)
              .attr("x2", x2)
              .attr("y2", y2);
          }
        }
      });
    },

    setupScrollbars: function () {
      if (!this.config.horizontal_scroll) return;

      const self = this;
      const scrollbar = this.element.find(".org-chart-scrollbar-horizontal");
      const thumb = scrollbar.find(".org-chart-scrollbar-thumb");

      function updateScrollbar() {
        const scrollLeft = self.viewport.scrollLeft();
        const scrollWidth = self.viewport[0].scrollWidth;
        const clientWidth = self.viewport.width();

        const thumbWidth = (clientWidth / scrollWidth) * scrollbar.width();
        const thumbLeft = (scrollLeft / scrollWidth) * scrollbar.width();

        thumb.css({
          width: thumbWidth + "px",
          left: thumbLeft + "px",
        });
      }

      this.viewport.on("scroll", updateScrollbar);
      updateScrollbar();

      // Scrollbar drag
      thumb.on("mousedown", function (e) {
        e.preventDefault();
        const startX = e.clientX - thumb.position().left;

        $(document).on("mousemove.scrollbar", function (e) {
          const newLeft = e.clientX - scrollbar.offset().left - startX;
          const scrollRatio = newLeft / scrollbar.width();
          self.viewport.scrollLeft(scrollRatio * self.viewport[0].scrollWidth);
        });

        $(document).on("mouseup.scrollbar", function () {
          $(document).off(".scrollbar");
        });
      });
    },

    setupLazyLoading: function () {
      if ("IntersectionObserver" in window) {
        const images = this.element.find('img[loading="lazy"]');
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              const img = entry.target;
              if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute("data-src");
              }
              observer.unobserve(img);
            }
          });
        });

        images.each(function () {
          observer.observe(this);
        });
      }
    },

    setupAnimations: function () {
      const duration = this.config.animation_duration || 300;

      // Animate elements on load
      this.element.find(".org-chart-element").each(function (index) {
        $(this).css({
          "animation-delay": index * 100 + "ms",
          "animation-duration": duration + "ms",
        });
      });
    },

    handleResize: function () {
      this.drawLines();
      this.setupScrollbars();
    },
  };

  // CSS for fullscreen mode
  $("<style>")
    .text(
      `
      .org-chart-fullscreen {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999 !important;
        border-radius: 0 !important;
      }

      .org-chart-fullscreen-active {
        overflow: hidden !important;
      }

      .org-chart-fullscreen .org-chart-viewport {
        height: calc(100vh - 80px) !important;
      }
    `
    )
    .appendTo("head");
})(jQuery, Drupal, drupalSettings);
