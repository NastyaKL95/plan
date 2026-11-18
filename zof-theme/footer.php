<?php
/**
 * Подвал сайта.
 *
 * @package zof
 */

$zof_organizers = zof_lines( zof_mod( 'organizers' ) );
$zof_email      = zof_mod( 'contact_email' );
$zof_phone      = zof_mod( 'contact_phone' );
$zof_address    = zof_mod( 'contact_address' );
$zof_date       = zof_event_date_label();
?>
</main>

<footer class="site-footer" id="contacts">
	<?php echo zof_ribbons_svg( 'ribbons-footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="wrap footer-grid">
		<div class="footer-about">
			<p class="footer-eyebrow"><?php echo esc_html( zof_mod( 'hero_eyebrow' ) ); ?></p>
			<p class="footer-theme"><?php echo esc_html( zof_mod( 'hero_title' ) ); ?></p>
			<?php if ( $zof_date ) : ?>
				<p class="footer-date"><?php echo zof_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $zof_date . ( zof_mod( 'event_place' ) ? ', ' . zof_mod( 'event_place' ) : '' ) ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $zof_organizers ) : ?>
			<div>
				<h2 class="footer-title">Организаторы</h2>
				<ul class="footer-list">
					<?php foreach ( $zof_organizers as $zof_org ) : ?>
						<li><?php echo esc_html( $zof_org ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $zof_email || $zof_phone || $zof_address ) : ?>
			<div>
				<h2 class="footer-title">Контакты</h2>
				<ul class="footer-list footer-contacts">
					<?php if ( $zof_email ) : ?>
						<li><?php echo zof_icon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="mailto:<?php echo esc_attr( antispambot( $zof_email ) ); ?>"><?php echo esc_html( antispambot( $zof_email ) ); ?></a></li>
					<?php endif; ?>
					<?php if ( $zof_phone ) : ?>
						<li><?php echo zof_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $zof_phone ) ); ?>"><?php echo esc_html( $zof_phone ); ?></a></li>
					<?php endif; ?>
					<?php if ( $zof_address ) : ?>
						<li><?php echo zof_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo nl2br( esc_html( $zof_address ) ); ?></span></li>
					<?php endif; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<div>
				<h2 class="footer-title">Разделы</h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-list',
						'depth'          => 1,
					)
				);
				?>
			</div>
		<?php endif; ?>
	</div>
	<div class="wrap footer-bottom">
		<span>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $zof_organizers ? end( $zof_organizers ) : get_bloginfo( 'name' ) ); ?></span>
		<a href="#siteHeader" class="footer-up">Наверх ↑</a>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
