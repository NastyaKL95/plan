<?php
/**
 * Страница не найдена.
 *
 * @package zof
 */

get_header();
get_template_part( 'template-parts/page-head', null, array( 'title' => 'Страница не найдена', 'lead' => 'Возможно, адрес изменился или страница ещё не опубликована.' ) );
?>
<div class="wrap page-body">
	<div class="empty-state">
		<p class="empty-code" aria-hidden="true">404</p>
		<?php get_search_form(); ?>
		<p><a class="btn btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную<?php echo zof_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
	</div>
</div>
<?php
get_footer();
