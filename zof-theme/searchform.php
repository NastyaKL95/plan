<?php
/**
 * Форма поиска.
 *
 * @package zof
 */

$zof_search_id = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $zof_search_id ); ?>">Поиск по сайту</label>
	<input type="search" id="<?php echo esc_attr( $zof_search_id ); ?>" class="search-field" placeholder="Поиск по сайту" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	<button type="submit" class="search-submit"><?php echo zof_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="screen-reader-text">Найти</span></button>
</form>
