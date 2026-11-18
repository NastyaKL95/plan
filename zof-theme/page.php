<?php
/**
 * Страница.
 *
 * @package zof
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/page-head', null, array( 'lead' => has_excerpt() ? esc_html( get_the_excerpt() ) : '' ) );
	?>
	<div class="wrap page-body">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="entry-cover"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages( array( 'before' => '<nav class="page-links">Страницы:', 'after' => '</nav>' ) );
				?>
			</div>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
