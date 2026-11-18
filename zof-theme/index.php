<?php
/**
 * Списки записей: новости, рубрики, архивы, поиск.
 *
 * @package zof
 */

get_header();

if ( is_search() ) {
	$zof_title = 'Поиск: ' . esc_html( get_search_query() );
} elseif ( is_home() ) {
	$zof_posts_page = (int) get_option( 'page_for_posts' );
	$zof_title      = $zof_posts_page ? esc_html( get_the_title( $zof_posts_page ) ) : 'Новости';
} elseif ( is_archive() ) {
	$zof_title = esc_html( wp_strip_all_tags( get_the_archive_title() ) );
} else {
	$zof_title = esc_html( get_bloginfo( 'name' ) );
}

get_template_part( 'template-parts/page-head', null, array( 'title' => $zof_title, 'lead' => is_archive() ? wp_strip_all_tags( get_the_archive_description() ) : '' ) );
?>
<div class="wrap page-body page-body-wide">
	<?php if ( is_search() ) : ?>
		<div class="search-again"><?php get_search_form(); ?></div>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="posts-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card' );
			endwhile;
			?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 1,
				'prev_text' => '←<span class="screen-reader-text"> Назад</span>',
				'next_text' => '<span class="screen-reader-text">Вперёд </span>→',
			)
		);
		?>
	<?php else : ?>
		<div class="empty-state">
			<p>Ничего не найдено. Попробуйте другой запрос или вернитесь на <a href="<?php echo esc_url( home_url( '/' ) ); ?>">главную</a>.</p>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
