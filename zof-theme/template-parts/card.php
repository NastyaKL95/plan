<?php
/**
 * Карточка записи в списках.
 *
 * @package zof
 */

?>
<article <?php post_class( 'card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'zof-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="card-body">
		<?php if ( 'post' === get_post_type() ) : ?>
			<time class="card-date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
		<?php endif; ?>
		<h2 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="card-text"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
	</div>
</article>
