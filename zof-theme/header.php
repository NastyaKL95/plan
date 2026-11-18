<?php
/**
 * Шапка сайта.
 *
 * @package zof
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content">Перейти к содержимому</a>

<header class="site-header" id="siteHeader">
	<div class="tricolor" aria-hidden="true"></div>
	<div class="wrap header-inner">
		<?php zof_brand(); ?>

		<button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="siteNav">
			<span class="nav-toggle-open"><?php echo zof_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="nav-toggle-close"><?php echo zof_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text">Меню</span>
		</button>

		<nav class="site-nav" id="siteNav" aria-label="Главное меню">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'depth'          => 2,
					'fallback_cb'    => 'zof_primary_menu_fallback',
				)
			);
			$cta_url = zof_mod( 'cta_url' );
			if ( $cta_url ) :
				?>
				<a class="btn btn-primary btn-sm header-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( zof_mod( 'cta_label' ) ); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</header>

<main id="content" class="site-main">
