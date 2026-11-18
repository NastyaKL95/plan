<?php
/**
 * Заголовок внутренней страницы.
 *
 * @package zof
 */

$zof_title = isset( $args['title'] ) ? $args['title'] : get_the_title();
$zof_lead  = isset( $args['lead'] ) ? $args['lead'] : '';
$zof_meta  = isset( $args['meta'] ) ? $args['meta'] : '';
?>
<header class="page-head">
	<?php echo zof_ribbons_svg( 'ribbons-head' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="wrap">
		<?php zof_breadcrumbs(); ?>
		<h1 class="page-title"><?php echo wp_kses_post( $zof_title ); ?></h1>
		<?php if ( $zof_lead ) : ?>
			<p class="page-lead"><?php echo wp_kses_post( $zof_lead ); ?></p>
		<?php endif; ?>
		<?php if ( $zof_meta ) : ?>
			<p class="page-meta"><?php echo wp_kses_post( $zof_meta ); ?></p>
		<?php endif; ?>
	</div>
</header>
