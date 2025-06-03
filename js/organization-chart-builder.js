/**
 * @file
 * JavaScript for organization chart builder interface.
 */

(function ($, Drupal, drupalSettings, once) {
  "use strict";

  /**
   * Organization Chart Builder behavior.
   */
  Drupal.behaviors.organizationChartBuilder = {
    attach: function (context, settings) {
      $(once("org-chart-builder", ".org-chart-builder", context)).each(
        function () {
          new OrganizationChartBuilder(this);
        }
      );
    },
  };

  /**
   * Organization Chart Builder class.
   */
  function OrganizationChartBuilder(element) {
    this.element = $(element);
    this.chartId = this.element.data("chart-id");
    this.canvas = this.element.find(".org-chart-builder-canvas");
    this.sidebar = this.element.find(".org-chart-builder-sidebar");
    this.elementForm = this.element.find("#element-properties-form");
    this.noSelection = this.element.find("#no-element-selected");

    this.selectedElement = null;
    this.elements = new Map();
    this.isDragging = false;
    this.dragOffset = { x: 0, y: 0 };
    this.snapGrid = 20;
    this.zoom = 1;
    this.hasUnsavedChanges = false;

    this.init();
  }

  OrganizationChartBuilder.prototype = {
    init: function () {
      this.loadElements();
      this.bindEvents();
      this.setupDragDrop();
      this.updateElementCount();
      this.setupAutoSave();

      // Show initial state
      this.showNoSelection();
    },

    loadElements: function () {
      const self = this;
      const chartData = drupalSettings.organizationChart || {};
      const elements = chartData.elements || [];

      elements.forEach(function (elementData) {
        self.elements.set(elementData.id, elementData);
        self.createElement(elementData);
      });

      this.updateLines();
    },

    bindEvents: function () {
      const self = this;

      // Toolbar buttons
      this.element
        .find('.org-chart-add-element[data-action="add-root"]')
        .on("click", function () {
          self.addRootElement();
        });

      this.element.find(".org-chart-zoom-fit").on("click", function () {
        self.fitToView();
      });

      this.element.find(".org-chart-zoom-reset").on("click", function () {
        self.resetView();
      });

      this.element.find("#org-chart-grid-snap").on("change", function () {
        self.snapGrid = parseInt($(this).val()) || 0;
        self.updateGridDisplay();
      });

      // Save and preview buttons
      this.element.find("#save-chart").on("click", function () {
        self.saveChart();
      });

      this.element.find("#preview-chart").on("click", function () {
        self.previewChart();
      });

      // Element form
      this.elementForm.find("#update-element").on("click", function () {
        self.updateSelectedElement();
      });

      this.elementForm.find("#delete-element").on("click", function () {
        self.deleteSelectedElement();
      });

      this.elementForm.find("#upload-image").on("click", function () {
        self.showImageUpload();
      });

      // Modal controls
      this.element
        .find("#file-upload-modal .org-chart-modal-close, #cancel-upload")
        .on("click", function () {
          self.hideImageUpload();
        });

      this.element.find("#confirm-upload").on("click", function () {
        self.confirmImageUpload();
      });

      this.element.find("#image-file-input").on("change", function () {
        self.previewImageUpload(this);
      });

      // Canvas events
      this.canvas.on("click", function (e) {
        if (e.target === this) {
          self.deselectElement();
        }
      });

      // Keyboard shortcuts
      $(document).on("keydown", function (e) {
        if (self.element.is(":visible")) {
          self.handleKeyboard(e);
        }
      });

      // Window events
      $(window).on("beforeunload", function () {
        if (self.hasUnsavedChanges) {
          return "You have unsaved changes. Are you sure you want to leave?";
        }
      });

      $(window).on("resize", function () {
        self.updateLines();
      });
    },

    setupDragDrop: function () {
      const self = this;

      // Make elements draggable
      this.canvas.on("mousedown", ".org-chart-builder-element", function (e) {
        if (
          $(e.target).closest(
            ".org-chart-builder-element-actions, .org-chart-builder-element-select"
          ).length
        ) {
          return;
        }

        self.startDrag($(this), e);
      });

      // Element selection
      this.canvas.on(
        "click",
        ".org-chart-builder-element-select",
        function (e) {
          e.stopPropagation();
          const element = $(this).closest(".org-chart-builder-element");
          self.selectElement(element);
        }
      );

      // Add child button
      this.canvas.on("click", ".org-chart-builder-add-child", function (e) {
        e.stopPropagation();
        const parentId = $(this).data("parent-id");
        self.addChildElement(parentId);
      });
    },

    startDrag: function (element, e) {
      e.preventDefault();
      const self = this;

      this.isDragging = true;
      element.addClass("dragging");
      this.canvas.addClass("dragging");

      const elementRect = element[0].getBoundingClientRect();
      const canvasRect = this.canvas[0].getBoundingClientRect();

      this.dragOffset = {
        x: e.clientX - elementRect.left,
        y: e.clientY - elementRect.top,
      };

      $(document).on("mousemove.builder", function (e) {
        self.updateDrag(element, e);
      });

      $(document).on("mouseup.builder", function () {
        self.endDrag(element);
      });
    },

    updateDrag: function (element, e) {
      const canvasRect = this.canvas[0].getBoundingClientRect();
      const scrollLeft = this.canvas.scrollLeft();
      const scrollTop = this.canvas.scrollTop();

      let x = e.clientX - canvasRect.left + scrollLeft - this.dragOffset.x;
      let y = e.clientY - canvasRect.top + scrollTop - this.dragOffset.y;

      // Snap to grid
      if (this.snapGrid > 0) {
        x = Math.round(x / this.snapGrid) * this.snapGrid;
        y = Math.round(y / this.snapGrid) * this.snapGrid;
      }

      // Keep within canvas bounds
      x = Math.max(0, Math.min(x, this.canvas.width() - element.outerWidth()));
      y = Math.max(
        0,
        Math.min(y, this.canvas.height() - element.outerHeight())
      );

      element.css("transform", `translate(${x}px, ${y}px)`);
      element.attr("data-x", x).attr("data-y", y);

      this.updateLines();
    },

    endDrag: function (element) {
      this.isDragging = false;
      element.removeClass("dragging");
      this.canvas.removeClass("dragging");

      $(document).off(".builder");

      // Update element data
      const elementId = element.data("element-id");
      const elementData = this.elements.get(elementId);
      if (elementData) {
        elementData.position_x = parseInt(element.attr("data-x"));
        elementData.position_y = parseInt(element.attr("data-y"));
        this.markUnsavedChanges();
      }

      this.updateLines();
    },

    selectElement: function (element) {
      // Deselect previous
      this.canvas.find(".org-chart-builder-element").removeClass("selected");

      // Select new
      element.addClass("selected");
      this.selectedElement = element;

      // Populate form
      const elementId = element.data("element-id");
      const elementData = this.elements.get(elementId);

      if (elementData) {
        this.elementForm.find("#element-title").val(elementData.title || "");
        this.elementForm
          .find("#element-description")
          .val(elementData.description || "");
        this.elementForm
          .find("#element-image")
          .val(elementData.image_url || "");
        this.elementForm.find("#element-link").val(elementData.link_url || "");
        this.elementForm.find("#element-theme").val(elementData.theme_id || "");

        this.showElementForm();
      }
    },

    deselectElement: function () {
      this.canvas.find(".org-chart-builder-element").removeClass("selected");
      this.selectedElement = null;
      this.showNoSelection();
    },

    showElementForm: function () {
      this.elementForm.show();
      this.noSelection.hide();
    },

    showNoSelection: function () {
      this.elementForm.hide();
      this.noSelection.show();
    },

    updateSelectedElement: function () {
      if (!this.selectedElement) return;

      const elementId = this.selectedElement.data("element-id");
      const elementData = this.elements.get(elementId);

      if (elementData) {
        elementData.title = this.elementForm.find("#element-title").val();
        elementData.description = this.elementForm
          .find("#element-description")
          .val();
        elementData.image_url = this.elementForm.find("#element-image").val();
        elementData.link_url = this.elementForm.find("#element-link").val();
        elementData.theme_id =
          this.elementForm.find("#element-theme").val() || null;

        this.updateElementDisplay(this.selectedElement, elementData);
        this.markUnsavedChanges();
      }
    },

    deleteSelectedElement: function () {
      if (!this.selectedElement) return;

      if (
        confirm(
          "Are you sure you want to delete this element and all its children?"
        )
      ) {
        const elementId = this.selectedElement.data("element-id");
        this.removeElementAndChildren(elementId);
        this.deselectElement();
        this.updateElementCount();
        this.updateLines();
        this.markUnsavedChanges();
      }
    },

    removeElementAndChildren: function (elementId) {
      // Find and remove children first
      this.elements.forEach((data, id) => {
        if (data.parent_id === elementId) {
          this.removeElementAndChildren(id);
        }
      });

      // Remove the element
      this.elements.delete(elementId);
      this.canvas.find(`[data-element-id="${elementId}"]`).remove();
    },

    addRootElement: function () {
      const elementData = {
        id: this.generateElementId(),
        chart_id: this.chartId,
        parent_id: null,
        title: "New Element",
        description: "",
        image_url: "",
        link_url: "",
        position_x: 100,
        position_y: 100,
        theme_id: null,
        weight: this.elements.size,
      };

      this.elements.set(elementData.id, elementData);
      const element = this.createElement(elementData);
      this.selectElement(element);
      this.updateElementCount();
      this.markUnsavedChanges();
    },

    addChildElement: function (parentId) {
      const parent = this.canvas.find(`[data-element-id="${parentId}"]`);
      if (parent.length === 0) return;

      const parentData = this.elements.get(parentId);
      if (!parentData) return;

      const elementData = {
        id: this.generateElementId(),
        chart_id: this.chartId,
        parent_id: parentId,
        title: "New Element",
        description: "",
        image_url: "",
        link_url: "",
        position_x: parentData.position_x + 250,
        position_y: parentData.position_y + 150,
        theme_id: null,
        weight: this.elements.size,
      };

      this.elements.set(elementData.id, elementData);
      const element = this.createElement(elementData);
      this.selectElement(element);
      this.updateElementCount();
      this.updateLines();
      this.markUnsavedChanges();
    },

    createElement: function (elementData) {
      const element = $(`
        <div class="org-chart-builder-element"
             data-element-id="${elementData.id}"
             data-parent-id="${elementData.parent_id || ""}"
             data-x="${elementData.position_x}"
             data-y="${elementData.position_y}"
             style="transform: translate(${elementData.position_x}px, ${
        elementData.position_y
      }px);">

          <div class="org-chart-builder-element-content">
            <div class="org-chart-builder-element-header">
              <button type="button" class="org-chart-builder-element-select">
                ${elementData.title || "Untitled"}
              </button>
              <div class="org-chart-builder-element-actions">
                <button type="button" class="org-chart-builder-add-child"
                        data-parent-id="${elementData.id}">+</button>
              </div>
            </div>

            ${
              elementData.image_url
                ? `
              <div class="org-chart-builder-element-image">
                <img src="${elementData.image_url}" alt="${elementData.title}">
              </div>
            `
                : ""
            }

            ${
              elementData.description
                ? `
              <div class="org-chart-builder-element-description">
                ${
                  elementData.description.length > 50
                    ? elementData.description.substring(0, 50) + "..."
                    : elementData.description
                }
              </div>
            `
                : ""
            }
          </div>

          <div class="org-chart-builder-drag-handle">⋮⋮</div>

          <div class="org-chart-builder-connections">
            <div class="org-chart-connection-point org-chart-connection-top"></div>
            <div class="org-chart-connection-point org-chart-connection-bottom"></div>
            <div class="org-chart-connection-point org-chart-connection-left"></div>
            <div class="org-chart-connection-point org-chart-connection-right"></div>
          </div>
        </div>
      `);

      this.canvas.find(".org-chart-elements").append(element);
      return element;
    },

    updateElementDisplay: function (element, elementData) {
      element
        .find(".org-chart-builder-element-select")
        .text(elementData.title || "Untitled");

      const imageContainer = element.find(".org-chart-builder-element-image");
      if (elementData.image_url) {
        if (imageContainer.length === 0) {
          element.find(".org-chart-builder-element-header").after(`
            <div class="org-chart-builder-element-image">
              <img src="${elementData.image_url}" alt="${elementData.title}">
            </div>
          `);
        } else {
          imageContainer
            .find("img")
            .attr("src", elementData.image_url)
            .attr("alt", elementData.title);
        }
      } else {
        imageContainer.remove();
      }

      const descContainer = element.find(
        ".org-chart-builder-element-description"
      );
      if (elementData.description) {
        const shortDesc =
          elementData.description.length > 50
            ? elementData.description.substring(0, 50) + "..."
            : elementData.description;

        if (descContainer.length === 0) {
          element.find(".org-chart-builder-element-content").append(`
            <div class="org-chart-builder-element-description">${shortDesc}</div>
          `);
        } else {
          descContainer.text(shortDesc);
        }
      } else {
        descContainer.remove();
      }
    },

    updateLines: function () {
      const svg = this.canvas.find(".org-chart-builder-lines");
      if (svg.length === 0) return;

      const self = this;
      this.elements.forEach((elementData, elementId) => {
        if (elementData.parent_id) {
          const element = this.canvas.find(`[data-element-id="${elementId}"]`);
          const parent = this.canvas.find(
            `[data-element-id="${elementData.parent_id}"]`
          );

          if (element.length && parent.length) {
            const line = svg.find(
              `[data-from="${elementData.parent_id}"][data-to="${elementId}"]`
            );

            const parentRect = parent[0].getBoundingClientRect();
            const elementRect = element[0].getBoundingClientRect();
            const canvasRect = this.canvas[0].getBoundingClientRect();

            const x1 =
              parentRect.left +
              parentRect.width / 2 -
              canvasRect.left +
              this.canvas.scrollLeft();
            const y1 =
              parentRect.bottom - canvasRect.top + this.canvas.scrollTop();
            const x2 =
              elementRect.left +
              elementRect.width / 2 -
              canvasRect.left +
              this.canvas.scrollLeft();
            const y2 =
              elementRect.top - canvasRect.top + this.canvas.scrollTop();

            if (line.length) {
              line.attr({ x1, y1, x2, y2 });
            } else {
              svg.append(`
                <line class="org-chart-builder-line"
                      data-from="${elementData.parent_id}"
                      data-to="${elementId}"
                      x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"
                      stroke="#6c757d" stroke-width="2" marker-end="url(#arrowhead)">
                </line>
              `);
            }
          }
        }
      });
    },

    updateElementCount: function () {
      this.element
        .find(".org-chart-element-count")
        .text(`Elements: ${this.elements.size}`);
    },

    updateGridDisplay: function () {
      if (this.snapGrid > 0) {
        this.canvas.css(
          "background-size",
          `${this.snapGrid}px ${this.snapGrid}px`
        );
      } else {
        this.canvas.css("background-size", "20px 20px");
      }
    },

    generateElementId: function () {
      return (
        "temp_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
      );
    },

    showImageUpload: function () {
      this.element.find("#file-upload-modal").attr("aria-hidden", "false");
      this.element.find("#image-file-input").focus();
    },

    hideImageUpload: function () {
      this.element.find("#file-upload-modal").attr("aria-hidden", "true");
      this.element.find("#image-file-input").val("");
      this.element.find("#image-preview").empty();
    },

    previewImageUpload: function (input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
          $("#image-preview").html(
            `<img src="${e.target.result}" alt="Preview">`
          );
        };
        reader.readAsDataURL(input.files[0]);
      }
    },

    confirmImageUpload: function () {
      const fileInput = this.element.find("#image-file-input")[0];
      if (fileInput.files && fileInput.files[0]) {
        // In a real implementation, you would upload the file to the server
        // For now, we'll use a placeholder URL
        const imageUrl = URL.createObjectURL(fileInput.files[0]);
        this.elementForm.find("#element-image").val(imageUrl);
        this.hideImageUpload();
      }
    },

    fitToView: function () {
      if (this.elements.size === 0) return;

      let minX = Infinity,
        minY = Infinity,
        maxX = -Infinity,
        maxY = -Infinity;

      this.elements.forEach((elementData) => {
        minX = Math.min(minX, elementData.position_x);
        minY = Math.min(minY, elementData.position_y);
        maxX = Math.max(maxX, elementData.position_x + 250); // Approximate element width
        maxY = Math.max(maxY, elementData.position_y + 120); // Approximate element height
      });

      const centerX = (minX + maxX) / 2;
      const centerY = (minY + maxY) / 2;

      this.canvas.scrollLeft(centerX - this.canvas.width() / 2);
      this.canvas.scrollTop(centerY - this.canvas.height() / 2);
    },

    resetView: function () {
      this.canvas.scrollLeft(0);
      this.canvas.scrollTop(0);
      this.zoom = 1;
      this.updateZoomDisplay();
    },

    updateZoomDisplay: function () {
      this.element
        .find(".org-chart-zoom-level")
        .text(`Zoom: ${Math.round(this.zoom * 100)}%`);
    },

    handleKeyboard: function (e) {
      switch (e.key) {
        case "Delete":
        case "Backspace":
          if (this.selectedElement && !$(e.target).is("input, textarea")) {
            e.preventDefault();
            this.deleteSelectedElement();
          }
          break;
        case "Escape":
          this.deselectElement();
          break;
        case "s":
          if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            this.saveChart();
          }
          break;
      }
    },

    markUnsavedChanges: function () {
      this.hasUnsavedChanges = true;
      this.element
        .find("#save-chart")
        .addClass("button--primary")
        .text("Save Chart *");
    },

    setupAutoSave: function () {
      const self = this;
      setInterval(function () {
        if (self.hasUnsavedChanges) {
          self.autoSave();
        }
      }, 30000); // Auto-save every 30 seconds
    },

    autoSave: function () {
      // Implement auto-save functionality
      console.log("Auto-saving chart...");
    },

    saveChart: function () {
      const self = this;
      const elementsData = Array.from(this.elements.values());

      $.ajax({
        url: "/organization-chart/element/ajax",
        method: "POST",
        data: {
          operation: "save_chart",
          chart_id: this.chartId,
          elements: JSON.stringify(elementsData),
        },
        success: function (response) {
          if (response.success) {
            self.hasUnsavedChanges = false;
            self.element
              .find("#save-chart")
              .removeClass("button--primary")
              .text("Save Chart");
            Drupal.announce("Chart saved successfully");
          } else {
            alert(
              "Error saving chart: " + (response.message || "Unknown error")
            );
          }
        },
        error: function () {
          alert("Error saving chart. Please try again.");
        },
      });
    },

    previewChart: function () {
      if (this.hasUnsavedChanges) {
        if (confirm("You have unsaved changes. Save before previewing?")) {
          this.saveChart();
        }
      }

      const previewUrl = `/organization-chart/${this.chartId}/preview`;
      window.open(previewUrl, "_blank", "width=1200,height=800");
    },
  };
})(jQuery, Drupal, drupalSettings, once);
