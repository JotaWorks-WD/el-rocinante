/**
 * Theme Settings — Admin JS
 *
 * Handles color picker initialization and media upload/remove
 * for the El Rocinante Theme Settings admin page.
 *
 * File:    theme-settings.js
 * Version: 1.1.0
 * Updated: 2026-07-26
 *
 * @package ElRocinante
 */

jQuery(document).ready(function ($) {
  // --------------------------------------------------------
  // COLOR PICKER
  // Initialize wp-color-picker on all EDITABLE color fields.
  //
  // Fields carrying data-roci-mirrored are excluded deliberately. Their colour
  // is supplied in code (roci_brand_palette), so the dashboard displays it but
  // does not own it. The readonly attribute alone would not be enough here:
  // wpColorPicker replaces the input with its own swatch and Clear controls and
  // writes back into it, so an initialised field stays editable through the
  // picker no matter what the attribute says. Skipping the init is what makes
  // readonly actually hold.
  // --------------------------------------------------------

  $(".roci-color-picker:not([data-roci-mirrored])").wpColorPicker();

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
});
