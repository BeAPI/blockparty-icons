<?php
/**
 * Minimal template. Kept deliberately bare so measurements reflect the plugin,
 * not theme rendering work.
 */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php wp_title( '' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
wp_footer();
?>
</body>
</html>
