/**
 * WP Media Manager — Admin JavaScript v1.1.0
 *
 * New in v1.1:
 *  - Duplicate detection + replace confirmation modal
 *  - Custom URL shown on each card with one-click copy
 *  - Inline duplicate warning beneath output preview
 *  - Duplicate check fires on custom_name blur and before save
 *
 * Depends on: jQuery, wp.media, wpmmData (localized via PHP)
 */

/* global wpmmData, wp */
(function ($) {
  "use strict";

  // ── State ──────────────────────────────────────────────────────────────
  const state = {
    page: 1,
    perPage: 20,
    search: "",
    total: 0,
    pages: 1,
    editing: null, // entry id being edited, or null
    selected: null, // currently selected media data object
    dupCheckTimer: null,
    pendingDupId: null, // duplicate row id found during check
  };

  // ── DOM refs ───────────────────────────────────────────────────────────
  const $modal = $("#wpmm-modal");
  const $dupModal = $("#wpmm-dup-modal");
  const $previewModal = $("#wpmm-preview-modal");
  const $modalTitle = $("#wpmm-modal-title");
  const $saveBtn = $("#wpmm-modal-save");
  const $saveBtnLabel = $saveBtn.find(".wpmm-btn__label");
  const $grid = $("#wpmm-grid");
  const $empty = $("#wpmm-empty");
  const $loading = $("#wpmm-loading");
  const $pagination = $("#wpmm-pagination");
  const $searchInput = $("#wpmm-search");
  const $countLabel = $("#wpmm-count-label");

  // Modal form fields
  const $entryId = $("#wpmm-entry-id");
  const $attachmentId = $("#wpmm-attachment-id");
  const $originalUrl = $("#wpmm-original-url");
  const $fileType = $("#wpmm-file-type");
  const $customName = $("#wpmm-custom-name");
  const $customPath = $("#wpmm-custom-path");
  const $includeExt = $("#wpmm-include-ext");
  const $urlInput = $("#wpmm-url-input");
  const $urlError = $("#wpmm-url-error");
  const $mediaPreview = $("#wpmm-media-preview");
  const $selectedInfo = $("#wpmm-selected-info");
  const $selectedName = $("#wpmm-selected-filename");
  const $outputPreview = $("#wpmm-output-preview-value");

  // Duplicate modal
  const $dupMessage = $("#wpmm-dup-message, .wpmm-dup-message");
  const $dupTargetId = $("#wpmm-dup-target-id");

  // redirect rules modal
  const $rulesModal = $("#wpmm-rules-modal");

  // ── Initialise ─────────────────────────────────────────────────────────
  function init() {
    bindEvents();

    // if only load media manager page
    if ($grid.length) {
      fetchEntries();
    }

    // if only load redirect rules page

    if ($("#wpmm-rules-grid").length) {
      fetchRedirectRules();
    }
  }

  // ── Event Binding ──────────────────────────────────────────────────────
  function bindEvents() {
    $("#wpmm-add-new, #wpmm-add-new-empty").on("click", openAddModal);

    // Add/Edit modal close.
    $("#wpmm-modal-cancel").on("click", closeModal);
    $modal.find(".wpmm-modal__close").on("click", closeModal);
    $modal.find(".wpmm-modal__backdrop").on("click", closeModal);

    // Save button
    $saveBtn.on("click", handleSaveClick);

    // Duplicate confirmation modal.
    $("#wpmm-dup-cancel").on("click", closeDupModal);
    $dupModal.find(".wpmm-modal__backdrop").on("click", closeDupModal);
    $("#wpmm-dup-replace").on("click", handleReplaceConfirmed);

    // Preview modal close.
    $previewModal
      .find(".wpmm-preview-close, .wpmm-modal__backdrop")
      .on("click", closePreviewModal);

    // Media library.
    $("#wpmm-open-media-library").on("click", openMediaLibrary);

    // URL resolver.
    $("#wpmm-resolve-url").on("click", resolveUrl);
    $urlInput.on("keydown", function (e) {
      if (e.key === "Enter") resolveUrl();
    });

    // Clear selection.
    $("#wpmm-clear-media").on("click", clearMediaSelection);

    // Live output preview.
    $customName
      .add($customPath)
      .add($includeExt)
      .on("input change", updateOutputPreview);

    // Auto-slugify on blur
    $customName.on("blur", function () {
      const raw = $(this).val();
      const fileType = $fileType.val();

      if (raw) {
        if (fileType === "pdf") {
          const cleanPdf = raw
            .trim()
            .replace(/[^A-Za-z0-9\-\._\(\)\[\]\+\s]/g, "")
            .replace(/\s+/g, " ");
          $(this).val(cleanPdf);
        } else {
          $(this).val(slugify(raw));
        }
        updateOutputPreview();
      }
    });
    $customPath.on("blur", function () {
      const raw = $(this).val();
      if (raw) {
        const clean = raw
          .split("/")
          .map((s) => slugify(s))
          .filter(Boolean)
          .join("/");
        $(this).val(clean);
        updateOutputPreview();
      }
    });

    // Duplicate check fires 600ms after user stops typing.
    $customName.on("input", debounce(triggerDupCheck, 600));
    $customPath.on("input", debounce(triggerDupCheck, 600));

    // Search.
    $searchInput.on(
      "input",
      debounce(function () {
        state.search = $(this).val().trim();
        state.page = 1;
        fetchEntries();
      }, 400),
    );

    // Grid delegated events.
    $grid.on("click", ".wpmm-edit-btn", handleEditClick);
    $grid.on("click", ".wpmm-delete-btn", handleDeleteClick);
    $grid.on("click", ".wpmm-preview-btn", handlePreviewClick);
    $grid.on("click", ".wpmm-copy-btn", handleCopyUrl);

    // Keyboard Escape.
    $(document).on("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!$dupModal.attr("hidden")) {
        closeDupModal();
        return;
      }
      if (!$previewModal.attr("hidden")) {
        closePreviewModal();
        return;
      }
      if (!$modal.attr("hidden")) {
        closeModal();
        return;
      }
      if (!$rulesModal.attr("hidden")) {
        $rulesModal.attr("hidden", "");
        $("body").removeClass("wpmm-modal-open");
      }
      if (!$("#wpmm-replace-modal").attr("hidden")) {
        $("#wpmm-replace-modal").attr("hidden", "");
        $("body").removeClass("wpmm-modal-open");
      }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 🆕 REDIRECT RULES MANAGER EVENTS
    // ─────────────────────────────────────────────────────────────────────────
    $("#wpmm-add-rule-btn").on("click", function () {
      $("#wpmm-rule-id").val("");
      $("#wpmm-rule-source").val("");
      $("#wpmm-rule-target").val("");
      $("#wpmm-rule-type").val("301");
      $("#wpmm-rule-modal-title").text("Add Redirect Rule");
      $("#wpmm-rules-modal").removeAttr("hidden");
      $("body").addClass("wpmm-modal-open");
    });

    $(
      "#wpmm-rules-modal-close, #wpmm-rules-modal-cancel, #wpmm-rules-modal .wpmm-modal__backdrop",
    ).on("click", function () {
      $("#wpmm-rules-modal").attr("hidden", "");
      $("body").removeClass("wpmm-modal-open");
    });

    $("#wpmm-rules-modal-save").on("click", function () {
      const source = $("#wpmm-rule-source").val().trim();
      const target = $("#wpmm-rule-target").val().trim();
      const type = $("#wpmm-rule-type").val();

      if (!source) {
        alert("Source path is required.");
        return;
      }

      const payload = {
        action: "wpmm_save_redirect_rule",
        nonce: wpmmData.nonce,
        id: $("#wpmm-rule-id").val(),
        source_path: source,
        target_url: target,
        redirect_type: type,
      };

      $.post(wpmmData.ajaxUrl, payload)
        .done(function (res) {
          if (res.success) {
            $("#wpmm-rules-modal").attr("hidden", "");
            $("body").removeClass("wpmm-modal-open");
            fetchRedirectRules();
          } else {
            alert(res.data.message || "Save failed.");
          }
        })
        .fail(() => alert("Network error."));
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 🆕 REPLACE MEDIA MODAL LOGIC (DIRECT SYSTEM FILE UPLOAD FLOW)
    // ─────────────────────────────────────────────────────────────────────────
    let replaceCurrentFileObj = null; // Stored object for the old WordPress media file
    let replaceLocalFileObj = null; // Stored binary object for the new local system file

    // 1. Click event on the main page button opens the modal popup directly
    $("#wpmm-replace-media-btn").on("click", function () {
      $("#wpmm-replace-attachment-id").val("");
      $("#wpmm-replace-file-input").val(""); // Resets the file input field
      $("#wpmm-replace-current-preview").html(
        '<span class="dashicons dashicons-format-image" style="font-size:40px; color:#9ca3af;"></span><span style="font-size:11px;color:#6b7280;display:block;margin-top:5px;">Click to select a file</span>',
      );
      $("#wpmm-replace-new-preview").html(
        '<span class="dashicons dashicons-plus" style="font-size:30px; color:#9ca3af;"></span>',
      );
      $("#wpmm-replace-modal-submit").attr("disabled", true);

      replaceCurrentFileObj = null;
      replaceLocalFileObj = null;

      const now = new Date();
      const formattedNow = `(${now.getDate()}/${now.toLocaleString("en-US", { month: "short" })}/${now.getFullYear()} ${now.getHours()}:${String(now.getMinutes()).padStart(2, "0")})`;
      $("#wpmm-current-date-text").text(formattedNow);

      if (state.selected && state.selected.created_at) {
        $("#wpmm-old-date-text").text(`(${state.selected.created_at})`);
      }
      $("#wpmm-replace-modal").removeAttr("hidden");
      $("body").addClass("wpmm-modal-open");
    });

    // ─────────────────────────────────────────────────────────────────────────
    // 2. "Current Media" Click Event — Fixed for Preview & Icon Alignment
    // ─────────────────────────────────────────────────────────────────────────
    $("#wpmm-replace-current-preview").on("click", function (e) {
      e.preventDefault();
      $("#wpmm-replace-modal").attr("hidden", ""); // Temporary hide to prevent backdrop overlay conflict

      const currentFrame = wp.media({
        title: "Select Old Media File to Replace",
        button: { text: "Select Current File" },
        multiple: false,
      });

      currentFrame.on("select", function () {
        const attachment = currentFrame
          .state()
          .get("selection")
          .first()
          .toJSON();

        replaceCurrentFileObj = attachment;
        $("#wpmm-replace-attachment-id").val(attachment.id);

        // Cache buster timestamp
        const buster = wpmmData.cacheBuster ? "?v=" + wpmmData.cacheBuster : "";

        let previewHtml = "";

        let thumbnailIdUrl =
          attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : null;
        if (!thumbnailIdUrl && attachment.type === "image") {
          thumbnailIdUrl = attachment.url;
        }

        if (thumbnailIdUrl) {
          previewHtml = `<img src="${thumbnailIdUrl}${buster}" style="width:100%; height:100%; object-fit:cover;" />`;
        } else {
          previewHtml = `
            <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; width:100%; padding:5px; box-sizing:border-box;">
                <span class="dashicons dashicons-document" style="font-size:32px; width:32px; height:32px; color:#4b5563; margin:0;"></span>
                <span style="font-size:10px; color:#6b7280; display:block; margin-top:4px; word-break:break-all; max-width:90px; line-height:1.2; text-align:center;">${escHtml(attachment.filename)}</span>
            </div>`;
        }

        $("#wpmm-replace-current-preview").html(previewHtml);
        checkReplaceFormReady();
      });

      currentFrame.on("close", function () {
        $("#wpmm-replace-modal").removeAttr("hidden");
      });

      currentFrame.open();
    });

    // 3. Click event on "New Media" opens the native local system file explorer
    $("#wpmm-replace-new-preview").on("click", function () {
      if (!replaceCurrentFileObj) {
        alert(
          "Please click on 'Current Media' area first to select the old file.",
        );
        return;
      }
      $("#wpmm-replace-file-input").trigger("click"); // Triggers the system file manager window
    });

    // 4. Handles file selection from computer and generates a client-side preview
    $("#wpmm-replace-file-input").on("change", function (e) {
      const file = e.target.files[0];
      if (!file) return;

      replaceLocalFileObj = file;

      // If it is an image file, use FileReader to display a local preview
      if (file.type.startsWith("image/")) {
        const reader = new FileReader();
        reader.onload = function (event) {
          $("#wpmm-replace-new-preview").html(
            `<img src="${event.target.result}" style="width:100%; height:100%; object-fit:cover;" />`,
          );
        };
        reader.readAsDataURL(file);
      } else {
        // Displays a fallback icon for PDFs or non-image assets
        $("#wpmm-replace-new-preview").html(
          `<div style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="font-size:30px; color:#16a34a;"></span><span style="font-size:10px;display:block;word-break:break-all;padding:0 4px;">${escHtml(file.name)}</span></div>`,
        );
      }

      checkReplaceFormReady();
    });

    // Validates if both file sections are loaded before unlocking submit button
    function checkReplaceFormReady() {
      if (replaceCurrentFileObj && replaceLocalFileObj) {
        $("#wpmm-replace-modal-submit").removeAttr("disabled");
      } else {
        $("#wpmm-replace-modal-submit").attr("disabled", true);
      }
    }

    // Close modal window triggers
    $(
      "#wpmm-replace-modal-close, #wpmm-replace-modal-cancel, #wpmm-replace-modal .wpmm-modal__backdrop",
    ).on("click", function () {
      $("#wpmm-replace-modal").attr("hidden", "");
      $("body").removeClass("wpmm-modal-open");
    });

    // 5. Appends fields via FormData payload to securely ship binary data over AJAX
    $("#wpmm-replace-modal-submit").on("click", function () {
      if (!replaceCurrentFileObj || !replaceLocalFileObj) return;

      const $btn = $(this);
      $btn.attr("disabled", true).find(".wpmm-spinner").show();

      const formData = new FormData();
      formData.append("action", "wpmm_replace_media_file");
      formData.append("nonce", wpmmData.nonce);
      formData.append("current_attachment_id", replaceCurrentFileObj.id);

      // Append selected radio button structural modifiers
      formData.append(
        "replace_method",
        $('input[name="wpmm_replace_method"]:checked').val(),
      );
      formData.append(
        "date_method",
        $('input[name="wpmm_date_method"]:checked').val(),
      );
      formData.append("replacement_file", replaceLocalFileObj);
      formData.append(
        "keep_date",
        $("#wpmm-replace-keep-date").is(":checked") ? 1 : 0,
      );

      $.ajax({
        url: wpmmData.ajaxUrl,
        type: "POST",
        data: formData,
        processData: false, // Essential to prevent jQuery from corrupting binary format
        contentType: false,
        success: function (res) {
          $btn.removeAttr("disabled").find(".wpmm-spinner").hide();
          if (res.success) {
            $("#wpmm-replace-modal").attr("hidden", "");
            $("body").removeClass("wpmm-modal-open");

            // Hard refreshes current grid view and saves unique cache timestamp
            state.page = 1;
            wpmmData.cacheBuster = res.data.timestamp;
            fetchEntries();
            alert("File replaced successfully!");
          } else {
            alert(res.data.message || "Failed to replace the file.");
          }
        },
        error: function () {
          $btn.removeAttr("disabled").find(".wpmm-spinner").hide();
          alert("Network error.");
        },
      });
    });

    $(document).on("click", ".wpmm-edit-rule", function () {
      const id = $(this).data("id");
      const source = $(this).data("source");
      const target = $(this).data("target");
      const type = $(this).data("type");

      $("#wpmm-rule-id").val(id);
      $("#wpmm-rule-source").val(source);
      $("#wpmm-rule-target").val(target);
      $("#wpmm-rule-type").val(type);

      $("#wpmm-rule-modal-title").text("Edit Redirect Rule");
      $("#wpmm-rules-modal").removeAttr("hidden");
      $("body").addClass("wpmm-modal-open");
    });

    $(document).on("click", ".wpmm-delete-rule", function () {
      if (!confirm("Are you sure you want to delete this rule?")) return;
      const id = $(this).data("id");
      $.post(wpmmData.ajaxUrl, {
        action: "wpmm_delete_redirect_rule",
        nonce: wpmmData.nonce,
        id: id,
      }).done(function () {
        fetchRedirectRules();
      });
    });
  }

  // ── AJAX: Fetch Entries ────────────────────────────────────────────────
  function fetchEntries() {
    showLoading();

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_get_entries",
      nonce: wpmmData.nonce,
      search: state.search,
      per_page: state.perPage,
      page: state.page,
    })
      .done(function (res) {
        if (!res.success) {
          showToast(res.data?.message || "Error loading entries.", "error");
          showEmpty();
          return;
        }
        const d = res.data;
        state.total = d.total;
        state.pages = d.total_pages;
        state.page = d.page;
        renderEntries(d.entries);
        renderPagination();
        updateCountLabel();
      })
      .fail(function () {
        showToast("Network error. Please try again.", "error");
        showEmpty();
      });
  }

  // ── Render ─────────────────────────────────────────────────────────────
  function renderEntries(entries) {
    hideLoading();

    if (!entries || !entries.length) {
      showEmpty();
      return;
    }

    hideEmpty();
    $grid.empty();
    entries.forEach((e) => $grid.append(buildCard(e)));
    $grid.removeAttr("hidden");
  }

  function buildCard(entry) {
    // Thumbnail: use thumbnail URL if available, fall back to original_url
    // for images (PHP sets thumbnail=original_url when no WP thumb meta exists).

    // 🆕 Fix: Prevents image caching issues in grid cards as well
    const buster = wpmmData.cacheBuster ? "?v=" + wpmmData.cacheBuster : "";
    const thumbSrc = entry.thumbnail ? entry.thumbnail + buster : null;

    const thumb = thumbSrc
      ? `<img src="${escAttr(thumbSrc)}" alt="${escAttr(entry.custom_name || entry.filename)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" /><span class="dashicons ${escAttr(entry.icon || "dashicons-media-default")} wpmm-card__thumb-icon" aria-hidden="true" style="display:none"></span>`
      : `<span class="dashicons ${escAttr(entry.icon || "dashicons-media-default")} wpmm-card__thumb-icon" aria-hidden="true"></span>`;

    const label = entry.custom_name || entry.filename;

    return $(`
    <div class="wpmm-card" data-id="${escAttr(entry.id)}">
      <div class="wpmm-card__thumb">
        ${thumb}
        <span class="wpmm-card__type-badge">${escHtml(entry.file_type)}</span>
      </div>
      <div class="wpmm-card__body">
        <div class="wpmm-card__custom-name" title="${escAttr(label)}">${escHtml(label)}</div>
        <div class="wpmm-card__original-name" title="${escAttr(entry.filename)}">${escHtml(entry.filename)}</div>
        <div class="wpmm-card__output" title="${escAttr(entry.output_path)}">${escHtml(entry.output_path)}</div>
        <div class="wpmm-card__date">${escHtml(entry.created_at)}</div>
      </div>
      <div class="wpmm-card__url-strip">
        <span class="wpmm-card__url-text" title="${escAttr(entry.custom_url)}">${escHtml(entry.custom_url)}</span>
        <button class="wpmm-copy-btn" data-url="${escAttr(entry.custom_url)}" title="Copy custom URL">
          <span class="dashicons dashicons-clipboard" aria-hidden="true"></span>Copy
        </button>
      </div>
      <div class="wpmm-card__actions">
        <button class="wpmm-btn wpmm-btn--secondary wpmm-btn--sm wpmm-preview-btn"
          data-id="${escAttr(entry.id)}"
          data-url="${escAttr(entry.original_url)}"
          data-type="${escAttr(entry.file_type)}"
          title="Preview">
          <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
        </button>
        <button class="wpmm-btn wpmm-btn--secondary wpmm-btn--sm wpmm-edit-btn"
          data-id="${escAttr(entry.id)}">
          <span class="dashicons dashicons-edit" aria-hidden="true"></span> Edit
        </button>
        <button class="wpmm-btn wpmm-btn--danger wpmm-btn--sm wpmm-delete-btn"
          data-id="${escAttr(entry.id)}">
          <span class="dashicons dashicons-trash" aria-hidden="true"></span>
        </button>
      </div>
    </div>
  `);
  }

  // ── Copy URL ───────────────────────────────────────────────────────────
  function handleCopyUrl() {
    const url = $(this).data("url");
    const $btn = $(this);

    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(() => flashCopied($btn));
    } else {
      const $tmp = $("<textarea>").val(url).appendTo("body").select();
      document.execCommand("copy");
      $tmp.remove();
      flashCopied($btn);
    }
  }

  function flashCopied($btn) {
    const original = $btn.html();
    $btn.html(
      '<span class="dashicons dashicons-yes" aria-hidden="true"></span>' +
        wpmmData.i18n.copied,
    );
    setTimeout(() => $btn.html(original), 1800);
  }

  // ── Pagination ─────────────────────────────────────────────────────────
  function renderPagination() {
    $pagination.empty();
    if (state.pages <= 1) {
      $pagination.attr("hidden", "");
      return;
    }
    $pagination.removeAttr("hidden");

    const mkBtn = (label, page, active, disabled) => {
      return $(
        `<button class="wpmm-pagination__btn${active ? " wpmm-pagination__btn--active" : ""}">${label}</button>`,
      )
        .prop("disabled", !!disabled)
        .on("click", () => goToPage(page));
    };

    $pagination.append(
      mkBtn("&#8592; Prev", state.page - 1, false, state.page <= 1),
    );

    buildPageRange(state.page, state.pages).forEach((p) => {
      if (p === "…") {
        $pagination.append(
          '<span style="padding:0 4px;line-height:36px;color:#9ca3af">…</span>',
        );
      } else {
        $pagination.append(mkBtn(p, p, p === state.page, false));
      }
    });

    $pagination.append(
      mkBtn("Next &#8594;", state.page + 1, false, state.page >= state.pages),
    );
  }

  function buildPageRange(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const r = [1];
    if (current > 3) r.push("…");
    for (
      let i = Math.max(2, current - 1);
      i <= Math.min(total - 1, current + 1);
      i++
    )
      r.push(i);
    if (current < total - 2) r.push("…");
    r.push(total);
    return r;
  }

  function goToPage(page) {
    if (page < 1 || page > state.pages) return;
    state.page = page;
    fetchEntries();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function updateCountLabel() {
    if (!state.total) {
      $countLabel.text("");
      return;
    }
    const start = (state.page - 1) * state.perPage + 1;
    const end = Math.min(state.page * state.perPage, state.total);
    $countLabel.text(`${start}–${end} of ${state.total} entries`);
  }

  // ── Open modals ────────────────────────────────────────────────────────
  function openAddModal() {
    state.editing = null;
    resetModal();
    $modalTitle.text("Add Media Entry");
    $saveBtnLabel.text("Save Entry");
    openModal();
  }

  function openModal() {
    $modal.removeAttr("hidden");
    $("body").addClass("wpmm-modal-open");
    setTimeout(() => $customName.trigger("focus"), 50);
  }

  // Modals controls
  function closeModal() {
    $modal.attr("hidden", "");
    $("body").removeClass("wpmm-modal-open");
    state.pendingDupId = null;
  }

  function openDupModal(message, targetId) {
    $dupModal.find(".wpmm-dup-message").text(message);
    $dupTargetId.val(targetId);
    $dupModal.removeAttr("hidden");
    $("#wpmm-dup-replace").trigger("focus");
  }

  function closeDupModal() {
    $dupModal.attr("hidden", "");
    state.pendingDupId = null;
    setSavingState(false);
  }

  function closeTemplateModal() {
    $modal.attr("hidden", "");
  }

  function closePreviewModal() {
    $previewModal.attr("hidden", "");
    $("#wpmm-preview-body").empty();
    $("body").removeClass("wpmm-modal-open");
  }

  // ── Edit ───────────────────────────────────────────────────────────────
  function handleEditClick() {
    const id = $(this).data("id");

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_get_entry",
      nonce: wpmmData.nonce,
      id,
    })
      .done(function (res) {
        if (!res.success) {
          showToast(res.data?.message || "Could not load entry.", "error");
          return;
        }
        const entry = res.data.entry;
        state.editing = entry.id;
        resetModal();
        populateModal(entry);
        $modalTitle.text("Edit Media Entry");
        $saveBtnLabel.text("Update Entry");
        openModal();
      })
      .fail(() => showToast("Network error.", "error"));
  }

  function populateModal(entry) {
    $entryId.val(entry.id);
    $attachmentId.val(entry.attachment_id);
    $originalUrl.val(entry.original_url);
    $fileType.val(entry.file_type);
    $customName.val(entry.custom_name);
    $customPath.val(entry.custom_path);
    $includeExt.prop("checked", entry.include_extension);
    $urlInput.val(entry.original_url);

    setMediaSelected({
      attachment_id: entry.attachment_id,
      original_url: entry.original_url,
      filename: entry.filename,
      file_type: entry.file_type,
      thumbnail: entry.thumbnail,
    });

    updateOutputPreview();
  }

  // ── Delete ─────────────────────────────────────────────────────────────
  function handleDeleteClick() {
    const id = $(this).data("id");
    if (!window.confirm(wpmmData.i18n.confirmDelete)) return;

    const $card = $(this).closest(".wpmm-card");
    $card.css("opacity", ".5");

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_delete_entry",
      nonce: wpmmData.nonce,
      id,
    })
      .done(function (res) {
        if (!res.success) {
          showToast(res.data?.message || "Delete failed.", "error");
          $card.css("opacity", 1);
          return;
        }
        showToast(res.data.message, "success");
        fetchEntries();
      })
      .fail(() => {
        showToast("Network error.", "error");
        $card.css("opacity", 1);
      });
  }

  // ── Preview ────────────────────────────────────────────────────────────
  function handlePreviewClick() {
    openPreview($(this).data("url"), $(this).data("type"));
  }

  function openPreview(url, type) {
    const $body = $("#wpmm-preview-body").empty();

    switch (type) {
      case "image":
        $body.html(
          `<div class="wpmm-preview-image"><img src="${escAttr(url)}" alt="Preview" /></div>`,
        );
        break;
      case "pdf":
        $body.html(
          `<iframe class="wpmm-preview-iframe" src="${escAttr(url)}" title="PDF Preview"></iframe>`,
        );
        break;
      case "video":
        $body.html(
          `<video class="wpmm-preview-video" controls><source src="${escAttr(url)}" /></video>`,
        );
        break;
      case "audio":
        $body.html(
          `<div style="padding:24px;"><audio controls style="width:100%"><source src="${escAttr(url)}" /></audio></div>`,
        );
        break;
      default:
        window.open(url, "_blank", "noopener,noreferrer");
        return;
    }

    $previewModal.removeAttr("hidden");
    $("body").addClass("wpmm-modal-open");
    $previewModal.find(".wpmm-modal__close").trigger("focus");
  }

  // ═══════════════════════════════════════════════════════════════════════
  // SAVE FLOW
  // ═══════════════════════════════════════════════════════════════════════
  function handleSaveClick() {
    if (!$originalUrl.val()) {
      showToast("Please select a media file first.", "error");
      return;
    }

    const name = $customName.val().trim();
    const path = $customPath.val().trim();
    const includeExt = $includeExt.is(":checked") ? 1 : 0;
    const origUrl = $originalUrl.val();

    // If no custom name, skip dup check (name will be auto-derived from file).
    if (!name) {
      executeSave();
      return;
    }

    setSavingState(true, wpmmData.i18n.checking);

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_check_duplicate",
      nonce: wpmmData.nonce,
      custom_name: name,
      custom_path: path.replace(/^\/|\/$/g, ""),
      include_extension: includeExt,
      original_url: origUrl,
      exclude_id: state.editing || 0,
    })
      .done(function (res) {
        if (!res.success) {
          setSavingState(false);
          showToast(res.data?.message || "Duplicate check failed.", "error");
          return;
        }

        if (res.data.is_duplicate) {
          setSavingState(false);
          state.pendingDupId = res.data.duplicate_id;
          openDupModal(res.data.message, res.data.duplicate_id);
        } else {
          executeSave();
        }
      })
      .fail(function () {
        setSavingState(false);
        showToast("Network error during duplicate check.", "error");
      });
  }

  /**
   * User clicked "Yes, Replace It" in the duplicate modal.
   */
  function handleReplaceConfirmed() {
    const targetId = $dupTargetId.val();
    if (!targetId) {
      closeDupModal();
      return;
    }

    closeDupModal();
    setSavingState(true);

    const payload = buildPayload();
    payload.action = "wpmm_replace_entry";
    payload.target_id = targetId;

    doSaveRequest(payload);
  }

  /**
   * Execute the actual add or update request (no duplicate found path).
   */
  function executeSave() {
    setSavingState(true);

    const payload = buildPayload();
    payload.action = state.editing ? "wpmm_update_entry" : "wpmm_add_entry";
    if (state.editing) payload.id = state.editing;

    doSaveRequest(payload);
  }

  function doSaveRequest(payload) {
    $.post(wpmmData.ajaxUrl, payload)
      .done(function (res) {
        setSavingState(false);

        if (!res.success) {
          showToast(res.data?.message || "Save failed.", "error");
          return;
        }

        // PHP found a duplicate during the final save — open replace modal.
        if (res.data && res.data.is_duplicate) {
          state.pendingDupId = res.data.duplicate_id;
          openDupModal(res.data.message, res.data.duplicate_id);
          return;
        }

        // Normal successful save.
        showToast(res.data.message, "success");
        closeModal();
        fetchEntries();
      })
      .fail(function () {
        setSavingState(false);
        showToast("Network error. Please try again.", "error");
      });
  }

  /**
   * Build common POST payload from form fields.
   */
  function buildPayload() {
    return {
      nonce: wpmmData.nonce,
      attachment_id: $attachmentId.val(),
      original_url: $originalUrl.val(),
      custom_name: $customName.val().trim(),
      custom_path: $customPath
        .val()
        .trim()
        .replace(/^\/|\/$/g, ""),
      include_extension: $includeExt.is(":checked") ? 1 : 0,
      file_type: $fileType.val(),
    };
  }

  function setSavingState(saving, label) {
    $saveBtn
      .toggleClass("is-loading", saving && !label)
      .toggleClass("is-checking", saving && !!label)
      .prop("disabled", saving);
    if (saving) {
      $saveBtnLabel.text(label || wpmmData.i18n.saving);
    } else {
      $saveBtnLabel.text(state.editing ? "Update Entry" : "Save Entry");
    }
  }

  // ── Inline duplicate check ───────────────────────────────────────────
  function triggerDupCheck() {
    const name = $customName.val().trim();
    const path = $customPath
      .val()
      .trim()
      .replace(/^\/|\/$/g, "");
    const includeExt = $includeExt.is(":checked");
    const origUrl = $originalUrl.val();

    $("#wpmm-dup-inline-warn").remove();

    if (!name || !origUrl) return;

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_check_duplicate",
      nonce: wpmmData.nonce,
      custom_name: name,
      custom_path: path,
      include_extension: includeExt ? 1 : 0,
      original_url: origUrl,
      exclude_id: state.editing || 0,
    }).done(function (res) {
      if (res.success && res.data.is_duplicate) {
        const $warn = $(`
					<div id="wpmm-dup-inline-warn" class="wpmm-inline-warning">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<span>A file already exists at this path. Saving will ask to replace it.</span>
					</div>
				`);
        $(".wpmm-output-preview").after($warn);
      }
    });
  }

  // ── Media Library ──────────────────────────────────────────────────────
  let wpMediaFrame = null;

  function openMediaLibrary() {
    if (wpMediaFrame) {
      wpMediaFrame.open();
      return;
    }

    wpMediaFrame = wp.media({
      title: wpmmData.i18n.selectMedia,
      button: { text: wpmmData.i18n.useSelected },
      multiple: false,
    });

    wpMediaFrame.on("select", function () {
      const attachment = wpMediaFrame.state().get("selection").first().toJSON();

      setMediaSelected({
        attachment_id: attachment.id,
        original_url: attachment.url,
        filename: attachment.filename,
        file_type: getFileCategory(attachment.mime),
        thumbnail:
          attachment.sizes?.thumbnail?.url ||
          (attachment.type === "image" ? attachment.url : null),
      });
    });

    wpMediaFrame.open();
  }

  // ── Resolve URL ────────────────────────────────────────────────────────
  function resolveUrl() {
    const url = $urlInput.val().trim();
    $urlError.attr("hidden", "").text("");

    if (!url) {
      showUrlError(wpmmData.i18n.invalidUrl);
      return;
    }

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_resolve_url",
      nonce: wpmmData.nonce,
      url,
    })
      .done(function (res) {
        if (!res.success) {
          showUrlError(res.data?.message || wpmmData.i18n.urlNotMedia);
          return;
        }
        setMediaSelected({
          attachment_id: res.data.attachment_id,
          original_url: res.data.original_url,
          filename: res.data.filename,
          file_type: res.data.file_type,
          thumbnail: res.data.thumbnail,
        });
      })
      .fail(() => showUrlError("Network error."));
  }

  // Error trigger
  function showUrlError(msg) {
    $urlError.text(msg).removeAttr("hidden");
  }

  // ── Media Selection ────────────────────────────────────────────────────
  function setMediaSelected(data) {
    state.selected = data;
    $attachmentId.val(data.attachment_id);
    $originalUrl.val(data.original_url);
    $fileType.val(data.file_type);
    $urlInput.val(data.original_url);
    $selectedName.text(data.filename);
    $urlError.attr("hidden", "");

    // custom name is empty — auto-fill with slugified filename (without extension)
    if (!$customName.val().trim()) {
      const baseName =
        data.filename.substring(0, data.filename.lastIndexOf(".")) ||
        data.filename;
      $customName.val(slugify(baseName));
    }

    $mediaPreview
      .empty()
      .removeClass(
        "wpmm-media-preview--empty wpmm-media-preview--has-image wpmm-media-preview--icon",
      );

    if (data.thumbnail) {
      $mediaPreview
        .addClass("wpmm-media-preview--has-image")
        .html(
          `<img src="${escAttr(data.thumbnail)}" alt="${escAttr(data.filename)}" />`,
        );
    } else {
      const icon = getDashicon(data.file_type);
      $mediaPreview
        .addClass("wpmm-media-preview--icon")
        .html(
          `<span class="dashicons ${icon} wpmm-media-preview__icon" aria-hidden="true"></span><span style="font-size:12px;color:#6b7280;margin-top:6px">${escHtml(data.filename)}</span>`,
        );
    }

    $selectedInfo.removeAttr("hidden");
    updateOutputPreview();
  }

  function clearMediaSelection() {
    state.selected = null;
    $attachmentId.val("");
    $originalUrl.val("");
    $fileType.val("");
    $urlInput.val("");
    $selectedInfo.attr("hidden", "");
    $mediaPreview
      .html(
        `
			<span class="dashicons dashicons-format-image wpmm-media-preview__placeholder-icon" aria-hidden="true"></span>
			<span class="wpmm-media-preview__placeholder-text">No file selected</span>
		`,
      )
      .addClass("wpmm-media-preview--empty")
      .removeClass("wpmm-media-preview--has-image wpmm-media-preview--icon");
    updateOutputPreview();
  }

  // ── Output Preview ─────────────────────────────────────────────────────
  function updateOutputPreview() {
    const name = $customName.val().trim();
    const path = $customPath
      .val()
      .trim()
      .replace(/^\/|\/$/g, "");
    const withExt = $includeExt.is(":checked");
    const origUrl = $originalUrl.val();
    const fileType = $fileType.val();

    if (!origUrl && !name) {
      $outputPreview.text("—");
      return;
    }

    let fileName = name || getBasename(origUrl);

    if (fileType !== "pdf") {
      const dotIdx = fileName.lastIndexOf(".");
      if (dotIdx > 0 && !withExt) fileName = fileName.substring(0, dotIdx);
    } else {
      fileName = fileName.replace(/\.[a-zA-Z0-9]{1,5}$/, "");
    }

    if (withExt && origUrl) {
      const ext = getExtension(origUrl);
      if (ext && !fileName.toLowerCase().endsWith("." + ext.toLowerCase()))
        fileName += "." + ext;
    }

    $outputPreview.text(path ? path + "/" + fileName : fileName);
  }

  // ── Modal Reset ────────────────────────────────────────────────────────
  function resetModal() {
    $entryId.val("");
    $attachmentId.val("");
    $originalUrl.val("");
    $fileType.val("");
    $customName.val("");
    $customPath.val("");
    $includeExt.prop("checked", true);
    $urlInput.val("");
    $urlError.attr("hidden", "").text("");
    $selectedInfo.attr("hidden", "");
    $("#wpmm-dup-inline-warn").remove();
    $mediaPreview
      .html(
        `
			<span class="dashicons dashicons-format-image wpmm-media-preview__placeholder-icon" aria-hidden="true"></span>
			<span class="wpmm-media-preview__placeholder-text">No file selected</span>
		`,
      )
      .addClass("wpmm-media-preview--empty")
      .removeClass("wpmm-media-preview--has-image wpmm-media-preview--icon");
    $outputPreview.text("—");
    setSavingState(false);
    state.selected = null;
    state.pendingDupId = null;
  }

  // ── Loading / Empty state ──────────────────────────────────────────────
  function showLoading() {
    $loading.show();
    $grid.attr("hidden", "");
    $empty.attr("hidden", "");
    $pagination.attr("hidden", "");
  }
  function hideLoading() {
    $loading.hide();
  }
  function showEmpty() {
    hideLoading();
    $empty.removeAttr("hidden");
    $grid.attr("hidden", "");
    $pagination.attr("hidden", "");
    $countLabel.text("");
  }
  function hideEmpty() {
    $empty.attr("hidden", "");
  }

  // ── Toasts ────────────────────────────────────────────────────────────
  function showToast(message, type) {
    type = type || "info";
    const icons = {
      success: "dashicons-yes-alt",
      error: "dashicons-dismiss",
      info: "dashicons-info",
    };
    const $t = $(`
			<div class="wpmm-toast wpmm-toast--${type}" role="alert">
				<span class="dashicons ${icons[type] || icons.info} wpmm-toast__icon" aria-hidden="true"></span>
				<span class="wpmm-toast__msg">${escHtml(message)}</span>
			</div>
		`);
    $("#wpmm-toast-container").append($t);
    $t.on("click", () => dismiss($t));
    setTimeout(() => dismiss($t), 4500);
  }

  function dismiss($t) {
    $t.css("animation", "wpmm-toast-out .3s ease forwards");
    setTimeout(() => $t.remove(), 320);
  }

  // ── Utilities ──────────────────────────────────────────────────────────
  function getFileCategory(mime) {
    if (!mime) return "other";
    if (mime.startsWith("image/")) return "image";
    if (mime.startsWith("video/")) return "video";
    if (mime.startsWith("audio/")) return "audio";
    if (mime === "application/pdf") return "pdf";
    return "document";
  }

  function getDashicon(cat) {
    return (
      {
        image: "dashicons-format-image",
        video: "dashicons-video-alt3",
        audio: "dashicons-controls-volumeon",
        pdf: "dashicons-pdf",
        document: "dashicons-media-document",
      }[cat] || "dashicons-media-default"
    );
  }

  // Base configurations
  function getBasename(url) {
    if (!url) return "";
    return url.split("/").pop().split("?")[0];
  }

  function getExtension(url) {
    const base = getBasename(url);
    const dot = base.lastIndexOf(".");
    return dot > 0 ? base.substring(dot + 1) : "";
  }

  /**
   * Convert a string to a URL-safe slug.
   * Mirrors Helper::sanitize_custom_name() in PHP.
   * "Home desk" → "home-desk"
   */
  function slugify(str) {
    const fileType = $("#wpmm-file-type").val(); // current media type

    if (fileType === "pdf") {
      return String(str || "")
        .trim()
        .replace(/\.[a-zA-Z0-9]{1,5}$/, "") // again remove extension if user included it in custom name
        .replace(/[^A-Za-z0-9\-\._\(\)\[\]\+\s]/g, "") // remove unsafe characters
        .replace(/\s+/g, " "); // normalize whitespace
    }

    // for other file types, use the old slug logic
    return String(str || "")
      .trim()
      .replace(/\.[a-zA-Z0-9]{1,5}$/, "")
      .replace(/[\s_]+/g, "-")
      .replace(/[^a-z0-9\-]/gi, "")
      .toLowerCase()
      .replace(/-+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  function escHtml(str) {
    return $("<div>")
      .text(String(str || ""))
      .html();
  }

  function escAttr(str) {
    return String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function debounce(fn, delay) {
    let t;
    return function () {
      const ctx = this,
        args = arguments;
      clearTimeout(t);
      t = setTimeout(() => fn.apply(ctx, args), delay);
    };
  }

  // ─────────────────────────────────────────────────────────────────────────
  // 🆕 REDIRECT RULES FETCH & RENDER LOGIC
  // ─────────────────────────────────────────────────────────────────────────
  function fetchRedirectRules() {
    $("#wpmm-rules-loading").show();
    $("#wpmm-rules-grid").hide();
    $("#wpmm-rules-empty").hide();

    $.post(wpmmData.ajaxUrl, {
      action: "wpmm_get_redirect_rules",
      nonce: wpmmData.nonce,
    }).done(function (res) {
      $("#wpmm-rules-loading").hide();
      if (res.success && res.data.rules.length) {
        const $gridDiv = $("#wpmm-rules-grid").empty().show();
        res.data.rules.forEach(function (rule) {
          const badgeColor =
            rule.redirect_type == "404"
              ? "background:#dc2626;"
              : "background:#16a34a;";

          const targetDisplay =
            rule.redirect_type == "404"
              ? "<i>Shows 404 Page Template</i>"
              : escHtml(rule.target_url);

          const $card = $(`
						<div class="wpmm-card">
							<div class="wpmm-card__body" style="padding:20px;">
								<div class="wpmm-card__custom-name" style="font-size:15px;color:#111827;font-weight:700;">/${escHtml(rule.source_path)}</div>
								<div class="wpmm-card__output" style="margin:10px 0; background:#f3f4f6; color:#4b5563;font-size:11px;word-break:break-all;" title="${escAttr(rule.target_url)}">➔ ${escHtml(targetDisplay)}</div>
								
								<div style="display:flex; justify-content:between; align-items:center; margin-top:10px; margin-bottom:10px;">
									<span class="wpmm-card__type-badge" style="position:static; display:inline-block; ${badgeColor}">${escHtml(rule.redirect_type)}</span>
									
									<span style="font-size:11px; color:#6b7280; font-weight:600; background:#f3f4f6; padding:3px 8px; border-radius:4px;display: flex;justify-content: center;align-items: center;margin-left: 5px;">
										<span class="dashicons dashicons-chart-bar" style="font-size:14px; width:14px; height:14px; margin-right:3px; margin-top:-2px;"></span>
										Total Redirects: ${escHtml(rule.hits_count || 0)}
									</span>
								</div>

								<div style="margin-top:15px; display:flex; gap:10px;">
									<button class="wpmm-btn wpmm-btn--secondary wpmm-btn--sm wpmm-edit-rule" data-id="${rule.id}" data-source="${escAttr(rule.source_path)}" data-target="${escAttr(rule.target_url)}" data-type="${escAttr(rule.redirect_type)}" style="flex:1;justify-content:center;"><span class="dashicons dashicons-edit" style="font-size:14px;margin-top:2px;"></span> Edit</button>
									<button class="wpmm-btn wpmm-btn--danger wpmm-btn--sm wpmm-delete-rule" data-id="${rule.id}" style="flex:1;justify-content:center;">Delete</button>
								</div>
							</div>
						</div>
					`);
          $gridDiv.append($card);
        });
      } else {
        $("#wpmm-rules-empty").show();
      }
    });
  }

  // ── Boot ───────────────────────────────────────────────────────────────
  $(document).ready(init);
})(jQuery);
