<?php
/**
 * Footer — Site Footer Template
 *
 * Closes the document. Loads template-parts/footer/site-footer.php (the
 * visible footer: copyright line and the optional 'footer' nav menu), then
 * fires wp_footer() and closes </body></html>. Contains no action hooks.
 *
 * v1.2.0: the visible markup moved to that part, byte-for-byte. A child
 * overrides the PART to change the footer, and leaves this file alone, so
 * wp_footer() and the document close stay parent-owned. Output is identical
 * when no child overrides the part.
 *
 * File:    footer.php
 * Version: 1.2.0
 * Updated: 2026-10-09
 *
 * @package ElRocinante
 */

get_template_part( 'template-parts/footer/site-footer' );
?>

<?php wp_footer(); ?>
</body>
</html>
