/**
 * Theme Settings — Admin JS
 *
 * Handles media upload/remove for the Theme Settings admin page,
 * per-type field group visibility on the Business tab, and the
 * repeatable amenity rows within it.
 *
 * File:    theme-settings.js
 * Version: 1.4.1
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

jQuery(document).ready(function ($) {
  // --------------------------------------------------------
  // COLOR PICKER — REMOVED, deliberately.
  //
  // The Design tab's colour fields are now static locked displays: a swatch,
  // the hex, and a hidden input carrying the stored value. Nothing on the tab
  // is an editable colour input, so there is nothing left to initialise and the
  // wpColorPicker() call that stood here matched zero elements.
  //
  // The wp-color-picker style and script dependency were removed from
  // roci_settings_enqueue() at v1.4.1 (parent 7.3.1). Nothing initialises a
  // picker on any tab, and the media/logo uploader depends on
  // wp_enqueue_media(), not on the picker.
  // --------------------------------------------------------

  // --------------------------------------------------------
  // MEDIA UPLOAD
  // Opens WP media library on button click
  // --------------------------------------------------------

  $(document).on("click", ".roci-media-upload", function (e) {
    e.preventDefault();

    var $btn = $(this);
    var targetId = $btn.data("target");
    var previewId = $btn.data("preview");

    var frame = wp.media({
      title: "Select Image",
      multiple: false,
      library: { type: "image" },
    });

    frame.on("select", function () {
      var attachment = frame.state().get("selection").first().toJSON();
      $("#" + targetId).val(attachment.url);
      $("#" + previewId)
        .attr("src", attachment.url)
        .addClass("has-image");
    });

    frame.open();
  });

  // --------------------------------------------------------
  // MEDIA REMOVE
  // Clears the hidden input and hides the preview image
  // --------------------------------------------------------

  $(document).on("click", ".roci-media-remove", function (e) {
    e.preventDefault();

    var $btn = $(this);
    var targetId = $btn.data("target");
    var previewId = $btn.data("preview");

    $("#" + targetId).val("");
    $("#" + previewId)
      .attr("src", "")
      .removeClass("has-image");
  });

  // --------------------------------------------------------
  // BUSINESS TYPE — PER-TYPE FIELD GROUP VISIBILITY
  //
  // DISPLAY ONLY. Every .roci-type-group stays in the DOM and submits on every
  // save. roci_sanitize_business() rebuilds the option row from $_POST, so a
  // field that does not submit is stored as '' — hiding a group by removing it
  // from the page would silently destroy that type's saved values on the next
  // save. Never convert this to markup removal.
  //
  // .is-hidden is added HERE and nowhere else. The PHP renders no hiding class,
  // so if this file fails to load every group stays visible — the safe
  // direction: the admin sees inapplicable fields rather than losing data.
  // --------------------------------------------------------

  function rociSyncTypeGroups() {
    var $select = $("#roci_biz_type");
    if (!$select.length) return; // Business tab not active — nothing to sync

    var selected = $select.val();

    $(".roci-type-group").each(function () {
      var $group = $(this);
      // attr(), not data(): data() coerces values and caches the first read.
      $group.toggleClass("is-hidden", $group.attr("data-type") !== selected);
    });
  }

  $(document).on("change", "#roci_biz_type", rociSyncTypeGroups);

  // Initial state — show the saved type's group, hide the rest.
  rociSyncTypeGroups();

  // --------------------------------------------------------
  // AMENITIES — REPEATABLE ROWS
  //
  // The row markup lives ONLY in tab-business.php. This script never builds a
  // row from a string: it clones the hidden .roci-amenity-row--template that
  // PHP already rendered, so a saved row and an added row cannot drift apart.
  //
  // Both handlers are delegated on $(document), which is what makes cloned rows
  // work with no rebinding — a row added after page load gets its Remove button
  // for free.
  // --------------------------------------------------------

  // Monotonic, never reused. Index collisions after a removal would silently
  // overwrite one row with another when PHP parses the POST, so a removed index
  // is retired rather than backfilled. The server reindexes on save anyway, so
  // gaps here are harmless — this counter only has to stay unique per page load.
  var rociAmenityIndex = $("#roci-amenities .roci-amenity-row").not(".roci-amenity-row--template").length;

  $(document).on("click", ".roci-amenity-add", function (e) {
    e.preventDefault();

    var $container = $("#roci-amenities");
    var $template = $container.find(".roci-amenity-row--template");
    if (!$container.length || !$template.length) return; // not on the Business tab

    var index = rociAmenityIndex++;
    var $row = $template.clone();

    $row.removeClass("roci-amenity-row--template").removeAttr("style");

    $row.find("input").each(function () {
      var $input = $(this);
      var name = $input.attr("name");

      // Re-enable: the prototype's inputs are disabled so it never submits.
      $input.prop("disabled", false);

      if (name) {
        $input.attr("name", name.replace("__INDEX__", index));
      }
    });

    $container.append($row);
    $row.find("input").first().focus();
  });

  $(document).on("click", ".roci-amenity-remove", function (e) {
    e.preventDefault();
    $(this).closest(".roci-amenity-row").remove();
  });
});
