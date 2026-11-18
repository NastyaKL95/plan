<?php
/**
 * Вспомогательные функции шаблонов.
 *
 * @package zof
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zof_month_genitive( $n ) {
	$months = array( 1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря' );
	return isset( $months[ $n ] ) ? $months[ $n ] : '';
}

/** Дата очного этапа в часовом поясе сайта или null, если не задана. */
function zof_event_datetime() {
	$raw = zof_mod( 'event_datetime' );
	if ( ! $raw ) {
		return null;
	}
	$dt = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw, wp_timezone() );
	return $dt ? $dt : null;
}

function zof_event_date_label( $with_year = true ) {
	$dt = zof_event_datetime();
	if ( ! $dt ) {
		return '';
	}
	$label = $dt->format( 'j' ) . ' ' . zof_month_genitive( (int) $dt->format( 'n' ) );
	return $with_year ? $label . ' ' . $dt->format( 'Y' ) : $label;
}

function zof_today() {
	return new DateTimeImmutable( 'today', wp_timezone() );
}

/**
 * Этапы с отметкой состояния: done, current или next.
 * Текущим считается первый этап, последний день которого ещё не прошёл.
 */
function zof_stages() {
	$today      = zof_today()->format( 'Y-m-d' );
	$stages     = array();
	$found_curr = false;
	for ( $i = 1; $i <= 4; $i++ ) {
		$text = trim( (string) zof_mod( "stage_{$i}_text" ) );
		if ( '' === $text ) {
			continue;
		}
		$end   = (string) zof_mod( "stage_{$i}_end" );
		$state = 'next';
		if ( $end && $end < $today ) {
			$state = 'done';
		} elseif ( ! $found_curr ) {
			$state      = 'current';
			$found_curr = true;
		}
		$stages[] = array(
			'dates' => (string) zof_mod( "stage_{$i}_dates" ),
			'text'  => $text,
			'state' => $state,
		);
	}
	return $stages;
}

/** Страницы для карточек конкурсов. */
function zof_direction_pages() {
	$ids = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$id = absint( get_theme_mod( "direction_{$i}_page", 0 ) );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$ids[] = $id;
		}
	}
	if ( $ids ) {
		return array_map( 'get_post', array_unique( $ids ) );
	}

	$exclude = array_filter( array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ), (int) get_option( 'wp_page_for_privacy_policy' ) ) );
	return get_pages(
		array(
			'parent'      => 0,
			'exclude'     => $exclude,
			'sort_column' => 'menu_order,post_title',
			'number'      => 4,
		)
	);
}

/** Число номинаций: пунктов первого списка на странице конкурса. */
function zof_count_nominations( $post ) {
	$content = (string) get_post_field( 'post_content', $post );
	if ( ! preg_match( '/<(ul|ol)\b[^>]*>(.*?)<\/\1>/is', $content, $m ) ) {
		return 0;
	}
	return preg_match_all( '/<li\b/i', $m[2] );
}

function zof_plural( $n, $one, $few, $many ) {
	$n10  = $n % 10;
	$n100 = $n % 100;
	if ( 1 === $n10 && 11 !== $n100 ) {
		return $one;
	}
	if ( $n10 >= 2 && $n10 <= 4 && ( $n100 < 12 || $n100 > 14 ) ) {
		return $few;
	}
	return $many;
}

/** Описание карточки: отрывок или начало текста без названий номинаций. */
function zof_direction_summary( $post ) {
	if ( has_excerpt( $post ) ) {
		return get_the_excerpt( $post );
	}
	$content = (string) get_post_field( 'post_content', $post );
	$content = preg_replace( '/<(ul|ol)\b.*?<\/\1>/is', '', $content );
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 22, '…' );
}

function zof_logos() {
	$logos = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$id = absint( get_theme_mod( "logo_{$i}", 0 ) );
		if ( $id && wp_attachment_is_image( $id ) ) {
			$logos[] = array(
				'id'  => $id,
				'url' => (string) get_theme_mod( "logo_{$i}_url", '' ),
			);
		}
	}
	return $logos;
}

function zof_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ) ) );
}

/** Логотип сайта или знак «ЗОФ» в цветах баннера. */
function zof_brand() {
	$name = get_bloginfo( 'name' );
	echo '<a class="brand" href="' . esc_url( home_url( '/' ) ) . '" rel="home">';
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'class' => 'brand-logo', 'alt' => '' ) );
	} else {
		echo '<span class="brand-mark" aria-hidden="true">' . zof_brand_mark_svg() . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$year = '';
	if ( preg_match( '/(\d{4})\s*$/u', $name, $m ) ) {
		$year = $m[1];
	}
	echo '<span class="brand-text"><span class="brand-name">ЗОФ' . ( $year ? ' <b>' . esc_html( $year ) . '</b>' : '' ) . '</span>';
	echo '<span class="brand-sub">Забайкальский образовательный форум</span></span>';
	echo '<span class="screen-reader-text">' . esc_html( $name ) . '</span>';
	echo '</a>';
}

function zof_brand_mark_svg() {
	return '<svg viewBox="0 0 48 48" width="44" height="44" focusable="false">'
		. '<circle cx="24" cy="24" r="22" fill="#fff"/>'
		. '<path d="M24 2a22 22 0 0 1 19.05 11" fill="none" stroke="#D62A2F" stroke-width="4" stroke-linecap="round"/>'
		. '<path d="M43.05 13A22 22 0 0 1 43.05 35" fill="none" stroke="#F5C218" stroke-width="4" stroke-linecap="round"/>'
		. '<path d="M43.05 35A22 22 0 0 1 4.95 35" fill="none" stroke="#8CC63F" stroke-width="4" stroke-linecap="round"/>'
		. '<path d="M4.95 35A22 22 0 0 1 24 2" fill="none" stroke="#1B7A3A" stroke-width="4" stroke-linecap="round"/>'
		. '<path d="M14 18.5 24 14l10 4.5-10 4.5z" fill="#1B7A3A"/>'
		. '<path d="M17.5 21v5.2c1.9 1.6 4.1 2.4 6.5 2.4s4.6-.8 6.5-2.4V21L24 24z" fill="#1B7A3A"/>'
		. '<path d="M12 35c3.5-4 7.5-6 12-6s8.5 2 12 6" fill="none" stroke="#F5C218" stroke-width="2.6" stroke-linecap="round"/>'
		. '</svg>';
}

/** Ленты как на баннере форума. */
function zof_ribbons_svg( $class = '' ) {
	$colors = array( '#D62A2F', '#EE7D1E', '#F5C218', '#8CC63F', '#1B7A3A' );
	$out    = '<svg class="ribbons ' . esc_attr( $class ) . '" viewBox="0 0 640 260" preserveAspectRatio="none" aria-hidden="true" focusable="false">';
	foreach ( $colors as $i => $color ) {
		$y    = 40 + $i * 22;
		$out .= '<path d="M-20 ' . ( $y + 150 ) . ' C 140 ' . ( $y + 40 ) . ', 340 ' . ( $y - 30 ) . ', 660 ' . ( $y + 10 ) . '" fill="none" stroke="' . $color . '" stroke-width="15" stroke-linecap="round"/>';
	}
	return $out . '</svg>';
}

function zof_icon( $name ) {
	$paths = array(
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
		'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'doc'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
		'phone'    => '<path d="M6.6 3.5h2.6l1.5 4-2 1.3a11 11 0 0 0 6.5 6.5l1.3-2 4 1.5v2.6a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="icon icon-' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

function zof_primary_menu_fallback() {
	echo '<ul class="menu">';
	echo '<li' . ( is_front_page() ? ' class="current-menu-item"' : '' ) . '><a href="' . esc_url( home_url( '/' ) ) . '">Главная</a></li>';
	foreach ( zof_direction_pages() as $page ) {
		$current = is_page( $page->ID ) ? ' class="current-menu-item"' : '';
		echo '<li' . $current . '><a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( zof_short_title( $page ) ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</ul>';
}

/** Короткое название для меню: «Образовательный салон» вместо полного названия с кавычками. */
function zof_short_title( $post ) {
	$title = get_the_title( $post );
	$short = trim( preg_replace( '/\s*«.*$/u', '', $title ) );
	return '' !== $short ? $short : $title;
}

function zof_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}
	echo '<nav class="crumbs" aria-label="Навигационная цепочка"><a href="' . esc_url( home_url( '/' ) ) . '">Главная</a>';
	if ( is_singular( 'post' ) ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			echo '<span aria-hidden="true">/</span><a href="' . esc_url( get_permalink( $posts_page ) ) . '">' . esc_html( get_the_title( $posts_page ) ) . '</a>';
		}
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor ) {
			echo '<span aria-hidden="true">/</span><a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . esc_html( get_the_title( $ancestor ) ) . '</a>';
		}
	}
	if ( is_singular() ) {
		echo '<span aria-hidden="true">/</span><span aria-current="page">' . esc_html( get_the_title( get_queried_object_id() ) ) . '</span>';
	}
	echo '</nav>';
}
