/**
 * Theme Settings — Admin JS
 *
 * Handles media upload/remove for the Theme Settings admin page.
 * for the El Rocinante Theme Settings admin page.
 *
 * File:    theme-settings.js
 * Version: 1.2.0
 * Updated: 2026-07-26
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
  // The wp-color-picker dependency is deliberately LEFT in place on the
  // roci-settings-js enqueue (settings-register.php:67, :72). It is a few KB on
  // admin settings screens only, and that enqueue is shared by all seven tabs —
  // removing it to save nothing risks the Identity tab's media/logo uploader for
  // no gain. Keeping it is harmless; removing it wrongly is not.
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
});
