/**
 * @file
 * JavaScript for organization chart admin interface.
 */

(function ($, Drupal, drupalSettings, once) {
  "use strict";

  /**
   * Organization Chart Admin behavior.
   */
  Drupal.behaviors.organizationChartAdmin = {
    attach: function (context, settings) {
      // Theme preview functionality
      $(once("theme-preview", ".organization-chart-theme-form", context)).each(
        function () {
          const form = $(this);
          const previewElement = $(
            '<div class="organization-chart-preview-box"><div class="organization-chart-preview-element"><div class="organization-chart-preview-image">👤</div><div class="organization-chart-preview-title">Sample Name</div><div class="organization-chart-preview-description">Sample Position</div></div></div>'
          );

          form.append(previewElement);

          // Update preview when settings change
          form.find("input, select").on("change input", function () {
            updateThemePreview(form, previewElement);
          });

          // Initial preview update
          updateThemePreview(form, previewElement);
        }
      );

      // Color picker enhancements
      $(once("color-picker", 'input[type="color"]', context)).each(function () {
        const input = $(this);
        const wrapper = $(
          '<div class="organization-chart-color-picker"></div>'
          );
          const textInput = $('<input type="text" class="form-text">');

          input.wrap(wrapper);
          textInput.val(input.val());
          input.after(textInput);

          input.on("change", function () {
            textInput.val(this.value);
          });

          textInput.on("change", function () {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
              input.val(this.value);
              input.trigger("change");
            }
          });
      });

      // Settings form enhancements
      $(once("settings-form", ".organization-chart-settings-form", context)).each(
        function () {
          const form = $(this);

          // Show/hide conditional fields
          form
            .find("input[data-controls]")
            .on("change", function () {
              const controls = $(this).data("controls").split(",");
              const isChecked = $(this).is(":checked");

              controls.forEach(function (selector) {
                const target = form.find(selector).closest(".form-item");
                if (isChecked) {
                  target.show();
                } else {
                  target.hide();
                }
              });
            })
            .trigger("change");

          // File upload preview
          form.find('input[type="file"]').on("change", function () {
            const file = this.files[0];
            if (file && file.type.startsWith("image/")) {
              const reader = new FileReader();
              reader.onload = function (e) {
                let preview = form.find(".file-preview");
                if (preview.length === 0) {
                  preview = $(
                    '<div class="file-preview"><img alt="Preview"></div>'
                  );
                  $(this).after(preview);
                }
                preview.find("img").attr("src", e.target.result);
              };
              reader.readAsDataURL(file);
            }
          });
        }
      );

      // Chart list enhancements
      $(once("chart-list", ".organization-chart-list", context)).each(
        function () {
          const list = $(this);

          // Bulk actions
          if (list.find(".form-checkbox").length > 0) {
            const bulkActions = $(
              '<div class="organization-chart-bulk-actions"><select class="form-select"><option value="">Bulk Actions...</option><option value="delete">Delete Selected</option><option value="duplicate">Duplicate Selected</option></select><button type="button" class="button">Apply</button></div>'
            );
            list.before(bulkActions);

            bulkActions.find("button").on("click", function () {
              const action = bulkActions.find("select").val();
              const selected = list.find(".form-checkbox:checked");

              if (action && selected.length > 0) {
                performBulkAction(action, selected);
              }
            });
          }

          // Quick edit functionality
          list
            .find(".organization-chart-item-title")
            .on("dblclick", function () {
              makeEditable($(this));
            });
        }
      );

      // Chart builder integration
      if (window.location.pathname.includes("/builder")) {
        setupBuilderIntegration();
      }

      // Shortcode generator
      $(
        once(
          "shortcode-generator",
          ".organization-chart-shortcode-generator",
          context
        )
      ).each(function () {
        setupShortcodeGenerator($(this));
      });

      // Help system
      $(once("help-trigger", ".organization-chart-help-trigger", context)).on(
        "click",
        function (e) {
          e.preventDefault();
          showHelpModal($(this).data("help-topic"));
        }
      );

      // Auto-save draft functionality
      $(once("auto-save", "form.organization-chart-form", context)).each(
        function () {
          setupAutoSave($(this));
        }
      );

      // Keyboard shortcuts
      $(once("keyboard-shortcuts", document, context)).on(
        "keydown",
        function (e) {
          if (e.ctrlKey || e.metaKey) {
            switch (e.key) {
              case "s":
                e.preventDefault();
                $('.organization-chart-form input[type="submit"]')
                  .first()
                  .click();
                break;
              case "n":
                if (e.shiftKey) {
                  e.preventDefault();
                  window.location.href = $(".organization-chart-add-link")
                    .first()
                    .attr("href");
                }
                break;
            }
          }
        }
      );
    },
  };

  /**
   * Updates theme preview based on form values.
   */
  function updateThemePreview(form, previewElement) {
    const preview = previewElement.find(".organization-chart-preview-element");
    const image = preview.find(".organization-chart-preview-image");
    const title = preview.find(".organization-chart-preview-title");
    const description = preview.find(".organization-chart-preview-description");

    // Apply styles from form
    const styles = {};

    // Background color
    const bgColor = form.find('input[name="background_color"]').val();
    if (bgColor) {
      styles.backgroundColor = bgColor;
    }

    // Border
    const borderColor = form.find('input[name="border_color"]').val();
    const borderWidth = form.find('input[name="border_width"]').val();
    const borderRadius = form.find('input[name="border_radius"]').val();

    if (borderColor) {
      styles.borderColor = borderColor;
    }
    if (borderWidth) {
      styles.borderWidth = borderWidth + "px";
    }
    if (borderRadius) {
      styles.borderRadius = borderRadius + "px";
    }

    // Text styling
    const textColor = form.find('input[name="text_color"]').val();
    const fontFamily = form.find('input[name="font_family"]').val();
    const fontSize = form.find('input[name="font_size"]').val();

    if (textColor) {
      styles.color = textColor;
    }
    if (fontFamily) {
      styles.fontFamily = fontFamily;
    }
    if (fontSize) {
      styles.fontSize = fontSize + "px";
    }

    preview.css(styles);

    // Image styling
    const imageWidth = form.find('input[name="image_width"]').val();
    const imageHeight = form.find('input[name="image_height"]').val();
    const imageBorderRadius = form
      .find('input[name="image_border_radius"]')
      .val();

    const imageStyles = {};
    if (imageWidth) {
      imageStyles.width = imageWidth + "px";
    }
    if (imageHeight) {
      imageStyles.height = imageHeight + "px";
    }
    if (imageBorderRadius) {
      imageStyles.borderRadius = imageBorderRadius + "px";
    }

    image.css(imageStyles);
  }

  /**
   * Makes an element editable inline.
   */
  function makeEditable(element) {
    const originalText = element.text();
    const input = $('<input type="text" class="form-text">').val(originalText);

    element.replaceWith(input);
    input.focus().select();

    function saveEdit() {
      const newText = input.val();
      if (newText !== originalText) {
        // Save via AJAX
        $.ajax({
          url: "/admin/structure/organization-chart/quick-edit",
          method: "POST",
          data: {
            id: element
              .closest(".organization-chart-list-item")
              .data("chart-id"),
            field: "name",
            value: newText,
          },
          success: function (response) {
            if (response.success) {
              element.text(newText);
              input.replaceWith(element);
              Drupal.announce("Chart name updated");
            } else {
              cancelEdit();
            }
          },
          error: function () {
            cancelEdit();
          },
        });
      } else {
        cancelEdit();
      }
    }

    function cancelEdit() {
      input.replaceWith(element);
    }

    input.on("blur", saveEdit);
    input.on("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        saveEdit();
      } else if (e.key === "Escape") {
        e.preventDefault();
        cancelEdit();
      }
    });
  }

  /**
   * Performs bulk actions on selected items.
   */
  function performBulkAction(action, selectedItems) {
    const ids = selectedItems
      .map(function () {
        return $(this)
          .closest(".organization-chart-list-item")
          .data("chart-id");
      })
      .get();

    let confirmMessage = "";
    switch (action) {
      case "delete":
        confirmMessage = `Are you sure you want to delete ${ids.length} chart(s)?`;
        break;
      case "duplicate":
        confirmMessage = `Duplicate ${ids.length} chart(s)?`;
        break;
    }

    if (confirm(confirmMessage)) {
      $.ajax({
        url: "/admin/structure/organization-chart/bulk-action",
        method: "POST",
        data: {
          action: action,
          ids: ids,
        },
        success: function (response) {
          if (response.success) {
            location.reload();
          } else {
            alert("Error: " + (response.message || "Unknown error"));
          }
        },
        error: function () {
          alert("An error occurred. Please try again.");
        },
      });
    }
  }

  /**
   * Sets up builder integration features.
   */
  function setupBuilderIntegration() {
    // Add quick access toolbar
    const toolbar = $(`
      <div class="organization-chart-quick-toolbar">
        <button type="button" class="button" data-action="undo" title="Undo (Ctrl+Z)">↶</button>
        <button type="button" class="button" data-action="redo" title="Redo (Ctrl+Y)">↷</button>
        <button type="button" class="button" data-action="copy" title="Copy (Ctrl+C)">📋</button>
        <button type="button" class="button" data-action="paste" title="Paste (Ctrl+V)">📄</button>
        <button type="button" class="button" data-action="delete" title="Delete (Del)">🗑</button>
      </div>
    `);

    $(".org-chart-builder-toolbar").append(toolbar);

    // Add context menu
    $(document).on("contextmenu", ".org-chart-builder-element", function (e) {
      e.preventDefault();
      showContextMenu(e.pageX, e.pageY, $(this));
    });
  }

  /**
   * Shows context menu for builder elements.
   */
  function showContextMenu(x, y, element) {
    // Remove existing menu
    $(".organization-chart-context-menu").remove();

    const menu = $(`
      <div class="organization-chart-context-menu" style="position: absolute; left: ${x}px; top: ${y}px; z-index: 10000;">
        <div class="context-menu-item" data-action="edit">Edit</div>
        <div class="context-menu-item" data-action="duplicate">Duplicate</div>
        <div class="context-menu-item" data-action="add-child">Add Child</div>
        <div class="context-menu-separator"></div>
        <div class="context-menu-item" data-action="delete">Delete</div>
      </div>
    `);

    $("body").append(menu);

    menu.find(".context-menu-item").on("click", function () {
      const action = $(this).data("action");
      handleContextAction(action, element);
      menu.remove();
    });

    // Remove menu when clicking elsewhere
    $(document).one("click", function () {
      menu.remove();
    });
  }

  /**
   * Handles context menu actions.
   */
  function handleContextAction(action, element) {
    const elementId = element.data("element-id");

    switch (action) {
      case "edit":
        element.find(".org-chart-builder-element-select").click();
        break;
      case "duplicate":
        // Trigger duplicate action
        $(document).trigger("org-chart:duplicate-element", [elementId]);
        break;
      case "add-child":
        element.find(".org-chart-builder-add-child").click();
        break;
      case "delete":
        element.find("#delete-element").click();
        break;
    }
  }

  /**
   * Sets up shortcode generator.
   */
  function setupShortcodeGenerator(container) {
    const form = $(`
      <div class="shortcode-generator-form">
        <div class="form-item">
          <label>Chart:</label>
          <select id="shortcode-chart-id" class="form-select">
            <option value="">Select a chart...</option>
          </select>
        </div>
        <div class="form-item">
          <label>Theme:</label>
          <select id="shortcode-theme-id" class="form-select">
            <option value="">Use default</option>
          </select>
        </div>
        <div class="form-item">
          <label><input type="checkbox" id="shortcode-show-title" checked> Show title</label>
        </div>
        <div class="form-item">
          <label><input type="checkbox" id="shortcode-show-controls" checked> Show controls</label>
        </div>
        <div class="form-item">
          <label>Generated shortcode:</label>
          <textarea id="shortcode-output" class="form-textarea" readonly rows="2"></textarea>
          <button type="button" class="button" id="copy-shortcode">Copy</button>
        </div>
      </div>
    `);

    container.append(form);

    // Load charts and themes
    loadShortcodeData(form);

    // Update shortcode on change
    form.find("select, input").on("change", function () {
      updateShortcode(form);
    });

    // Copy functionality
    form.find("#copy-shortcode").on("click", function () {
      const textarea = form.find("#shortcode-output")[0];
      textarea.select();
      document.execCommand("copy");
      Drupal.announce("Shortcode copied to clipboard");
    });
  }

  /**
   * Loads data for shortcode generator.
   */
  function loadShortcodeData(form) {
    $.ajax({
      url: "/admin/structure/organization-chart/shortcode-data",
      method: "GET",
      success: function (response) {
        const chartSelect = form.find("#shortcode-chart-id");
        const themeSelect = form.find("#shortcode-theme-id");

        // Populate charts
        response.charts.forEach(function (chart) {
          chartSelect.append(
            `<option value="${chart.id}">${chart.name}</option>`
          );
        });

        // Populate themes
        response.themes.forEach(function (theme) {
          themeSelect.append(
            `<option value="${theme.id}">${theme.name}</option>`
          );
        });
      },
    });
  }

  /**
   * Updates the generated shortcode.
   */
  function updateShortcode(form) {
    const chartId = form.find("#shortcode-chart-id").val();
    const themeId = form.find("#shortcode-theme-id").val();
    const showTitle = form.find("#shortcode-show-title").is(":checked");
    const showControls = form.find("#shortcode-show-controls").is(":checked");

    if (!chartId) {
      form.find("#shortcode-output").val("");
      return;
    }

    let shortcode = `[org_chart chart_id=${chartId}`;

    if (themeId) {
      shortcode += ` theme_id=${themeId}`;
    }

    if (!showTitle) {
      shortcode += " show_title=0";
    }

    if (!showControls) {
      shortcode += " show_controls=0";
    }

    shortcode += "]";

    form.find("#shortcode-output").val(shortcode);
  }

  /**
   * Shows help modal.
   */
  function showHelpModal(topic) {
    const modal = $(`
      <div class="organization-chart-help-modal">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
          <div class="modal-header">
            <h3>Help: ${topic}</h3>
            <button type="button" class="modal-close">&times;</button>
          </div>
          <div class="modal-body">
            <div class="loading">Loading help content...</div>
          </div>
        </div>
      </div>
    `);

    $("body").append(modal);

    // Load help content
    $.ajax({
      url: `/admin/help/organization-chart/${topic}`,
      success: function (response) {
        modal.find(".modal-body").html(response);
      },
      error: function () {
        modal.find(".modal-body").html("<p>Help content not available.</p>");
      },
    });

    // Close modal
    modal.find(".modal-close, .modal-backdrop").on("click", function () {
      modal.remove();
    });
  }

  /**
   * Sets up auto-save functionality.
   */
  function setupAutoSave(form) {
    let autoSaveTimer;
    let hasChanges = false;

    form.find("input, textarea, select").on("change input", function () {
      hasChanges = true;
      clearTimeout(autoSaveTimer);

      autoSaveTimer = setTimeout(function () {
        if (hasChanges) {
          performAutoSave(form);
        }
      }, 10000); // Auto-save after 10 seconds of inactivity
    });

    // Show indicator when there are unsaved changes
    function showUnsavedIndicator() {
      if (!form.find(".unsaved-indicator").length) {
        form.prepend(
          '<div class="unsaved-indicator">You have unsaved changes</div>'
        );
      }
    }

    function hideUnsavedIndicator() {
      form.find(".unsaved-indicator").remove();
    }

    function performAutoSave(form) {
      const formData = form.serialize();

      $.ajax({
        url: form.attr("action") + "/auto-save",
        method: "POST",
        data: formData,
        success: function (response) {
          if (response.success) {
            hasChanges = false;
            hideUnsavedIndicator();
            // Show brief confirmation
            const indicator = $(
              '<div class="auto-save-indicator">Auto-saved</div>'
            );
            form.prepend(indicator);
            setTimeout(function () {
              indicator.fadeOut(function () {
                indicator.remove();
              });
            }, 2000);
          }
        },
      });
    }
  }

  // Add CSS for dynamic elements
  $("<style>")
    .text(
      `
      .organization-chart-context-menu {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 120px;
      }

      .context-menu-item {
        padding: 0.5rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
      }

      .context-menu-item:hover {
        background: #f8f9fa;
      }

      .context-menu-item:last-child {
        border-bottom: none;
      }

      .context-menu-separator {
        height: 1px;
        background: #dee2e6;
        margin: 0.25rem 0;
      }

      .organization-chart-quick-toolbar {
        display: flex;
        gap: 0.25rem;
        margin-left: auto;
      }

      .organization-chart-help-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
      }

      .organization-chart-help-modal .modal-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
      }

      .organization-chart-help-modal .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        max-width: 90vw;
        max-height: 90vh;
        overflow: auto;
        min-width: 500px;
      }

      .organization-chart-help-modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #dee2e6;
      }

      .organization-chart-help-modal .modal-body {
        padding: 1.5rem;
      }

      .unsaved-indicator {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
      }

      .auto-save-indicator {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
      }
    `
    )
    .appendTo("head");
})(jQuery, Drupal, drupalSettings, once);
