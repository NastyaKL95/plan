<?php
/**
 * Тема «ЗОФ 2026».
 *
 * @package zof
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZOF_DIR', get_template_directory() );
define( 'ZOF_URI', get_template_directory_uri() );

require ZOF_DIR . '/inc/customizer.php';
require ZOF_DIR . '/inc/template-tags.php';

function zof_setup() {
	load_theme_textdomain( 'zof', ZOF_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 160,
			'width'       => 160,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'zof-card', 800, 500, true );

	register_nav_menus(
		array(
			'primary' => 'Главное меню',
			'footer'  => 'Меню в подвале',
		)
	);
}
add_action( 'after_setup_theme', 'zof_setup' );

function zof_asset_version( $relative ) {
	$path = ZOF_DIR . '/' . ltrim( $relative, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : wp_get_theme()->get( 'Version' );
}

function zof_enqueue_assets() {
	wp_enqueue_style( 'zof-theme', ZOF_URI . '/assets/css/theme.css', array(), zof_asset_version( 'assets/css/theme.css' ) );
	wp_enqueue_script( 'zof-theme', ZOF_URI . '/assets/js/theme.js', array(), zof_asset_version( 'assets/js/theme.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'zof_enqueue_assets' );

function zof_preload_fonts() {
	$fonts = array( 'oswald-cyrillic-600-normal.woff2', 'golos-text-cyrillic-400-normal.woff2' );
	foreach ( $fonts as $font ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( ZOF_URI . '/assets/fonts/' . $font ) );
	}
}
add_action( 'wp_head', 'zof_preload_fonts', 2 );

function zof_theme_color_meta() {
	echo '<meta name="theme-color" content="#FBF3DF">' . "\n";
}
add_action( 'wp_head', 'zof_theme_color_meta', 3 );

function zof_favicon_fallback() {
	if ( ! has_site_icon() ) {
		echo '<link rel="icon" href="' . esc_url( ZOF_URI . '/assets/img/favicon.svg' ) . '" type="image/svg+xml">' . "\n";
	}
}
add_action( 'wp_head', 'zof_favicon_fallback', 4 );

function zof_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'is-front';
	}
	return $classes;
}
add_filter( 'body_class', 'zof_body_classes' );

function zof_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'zof_excerpt_more' );

function zof_excerpt_length() {
	return 24;
}
add_filter( 'excerpt_length', 'zof_excerpt_length' );
