<?php
/**
 * Запись.
 *
 * @package zof
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part(
		'template-parts/page-head',
		null,
		array( 'meta' => '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date( 'j F Y' ) ) . '</time>' )
	);
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
		the_post_navigation(
			array(
				'prev_text' => '<span class="nav-label">Предыдущая</span><span class="nav-title">%title</span>',
				'next_text' => '<span class="nav-label">Следующая</span><span class="nav-title">%title</span>',
			)
		);

		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>
	<?php
endwhile;

get_footer();
