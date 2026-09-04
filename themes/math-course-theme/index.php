<?php
/**
 * Math Course Theme - fallback template.
 *
 * WordPress requires an index.php for a standalone classic theme.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main class="mc-container">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<div><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( '暂无内容。', 'mathcourse' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
