<?php
/**
 * Главная страница форума.
 *
 * @package zof
 */

get_header();

$zof_event      = zof_event_datetime();
$zof_now        = new DateTimeImmutable( 'now', wp_timezone() );
$zof_is_past    = $zof_event && $zof_event <= $zof_now;
$zof_stages     = zof_mod( 'show_stages' ) ? zof_stages() : array();
$zof_current    = null;
foreach ( $zof_stages as $zof_stage ) {
	if ( 'current' === $zof_stage['state'] ) {
		$zof_current = $zof_stage;
		break;
	}
}
$zof_directions = zof_direction_pages();
$zof_cta_url    = zof_mod( 'cta_url' );
$zof_doc_url    = zof_mod( 'doc_url' );
$zof_logos      = zof_logos();
?>

<section class="hero">
	<?php echo zof_ribbons_svg( 'ribbons-hero-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo zof_ribbons_svg( 'ribbons-hero-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="wrap hero-grid">
		<div class="hero-copy">
			<p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span><?php echo esc_html( zof_mod( 'hero_eyebrow' ) ); ?><?php echo $zof_event ? ' · ' . esc_html( $zof_event->format( 'Y' ) ) : ''; ?></p>
			<h1 class="hero-title"><?php echo esc_html( zof_mod( 'hero_title' ) ); ?></h1>
			<?php if ( zof_mod( 'hero_lead' ) ) : ?>
				<p class="hero-lead"><?php echo esc_html( zof_mod( 'hero_lead' ) ); ?></p>
			<?php endif; ?>
			<div class="hero-actions">
				<?php if ( $zof_cta_url ) : ?>
					<a class="btn btn-primary" href="<?php echo esc_url( $zof_cta_url ); ?>"><?php echo esc_html( zof_mod( 'cta_label' ) ); ?><?php echo zof_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php elseif ( $zof_directions ) : ?>
					<a class="btn btn-primary" href="#directions"><?php echo esc_html( zof_mod( 'directions_title' ) ); ?><?php echo zof_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<?php endif; ?>
				<?php if ( $zof_doc_url ) : ?>
					<a class="btn btn-ghost" href="<?php echo esc_url( $zof_doc_url ); ?>"><?php echo zof_icon( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( zof_mod( 'doc_label' ) ); ?></a>
				<?php elseif ( $zof_stages ) : ?>
					<a class="btn btn-ghost" href="#stages"><?php echo zof_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( zof_mod( 'stages_title' ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $zof_event ) : ?>
			<aside class="event-card" aria-label="Дата форума">
				<p class="event-card-label"><?php echo esc_html( zof_mod( 'event_note' ) ); ?></p>
				<p class="event-card-date">
					<span class="event-day"><?php echo esc_html( $zof_event->format( 'j' ) ); ?></span>
					<span class="event-month"><?php echo esc_html( zof_month_genitive( (int) $zof_event->format( 'n' ) ) ); ?><br><?php echo esc_html( $zof_event->format( 'Y' ) ); ?></span>
				</p>
				<?php if ( zof_mod( 'event_place' ) ) : ?>
					<p class="event-card-place"><?php echo zof_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( zof_mod( 'event_place' ) ); ?></p>
				<?php endif; ?>

				<?php if ( zof_mod( 'show_countdown' ) && ! $zof_is_past ) : ?>
					<?php
					$zof_left  = max( 0, $zof_event->getTimestamp() - $zof_now->getTimestamp() );
					$zof_parts = array(
						'days'    => array( intdiv( $zof_left, 86400 ), array( 'день', 'дня', 'дней' ) ),
						'hours'   => array( intdiv( $zof_left % 86400, 3600 ), array( 'час', 'часа', 'часов' ) ),
						'minutes' => array( intdiv( $zof_left % 3600, 60 ), array( 'минута', 'минуты', 'минут' ) ),
					);
					?>
					<div class="countdown" data-deadline="<?php echo esc_attr( $zof_event->format( DATE_ATOM ) ); ?>" role="timer" aria-live="off">
						<?php foreach ( $zof_parts as $zof_unit => $zof_part ) : ?>
							<div class="countdown-cell">
								<span class="countdown-num" data-unit="<?php echo esc_attr( $zof_unit ); ?>"><?php echo esc_html( str_pad( (string) $zof_part[0], 2, '0', STR_PAD_LEFT ) ); ?></span>
								<span class="countdown-unit" data-forms="<?php echo esc_attr( implode( ',', $zof_part[1] ) ); ?>"><?php echo esc_html( zof_plural( $zof_part[0], $zof_part[1][0], $zof_part[1][1], $zof_part[1][2] ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php elseif ( $zof_is_past ) : ?>
					<p class="event-card-done"><?php echo zof_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>Форум состоялся. Спасибо участникам!</p>
				<?php endif; ?>

				<?php if ( $zof_current && ! $zof_is_past ) : ?>
					<p class="event-card-now"><span>Сейчас</span><?php echo esc_html( $zof_current['text'] ); ?> · <?php echo esc_html( $zof_current['dates'] ); ?></p>
				<?php endif; ?>
			</aside>
		<?php endif; ?>
	</div>

	<?php if ( $zof_logos ) : ?>
		<div class="wrap logos" aria-label="Партнёры форума">
			<?php foreach ( $zof_logos as $zof_logo ) : ?>
				<?php
				$zof_img = wp_get_attachment_image( $zof_logo['id'], 'medium', false, array( 'class' => 'logo-img', 'loading' => 'lazy' ) );
				if ( $zof_logo['url'] ) {
					echo '<a class="logo-item" href="' . esc_url( $zof_logo['url'] ) . '" target="_blank" rel="noopener">' . $zof_img . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<span class="logo-item">' . $zof_img . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php if ( $zof_stages ) : ?>
	<section class="section" id="stages" aria-labelledby="stagesTitle">
		<div class="wrap">
			<div class="section-head">
				<p class="section-kicker">Календарь</p>
				<h2 class="section-title" id="stagesTitle"><?php echo esc_html( zof_mod( 'stages_title' ) ); ?></h2>
			</div>
			<ol class="stages">
				<?php foreach ( $zof_stages as $zof_i => $zof_stage ) : ?>
					<li class="stage is-<?php echo esc_attr( $zof_stage['state'] ); ?>"<?php echo 'current' === $zof_stage['state'] ? ' aria-current="step"' : ''; ?>>
						<span class="stage-marker" aria-hidden="true">
							<?php echo 'done' === $zof_stage['state'] ? zof_icon( 'check' ) : esc_html( (string) ( $zof_i + 1 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span class="stage-dates"><?php echo esc_html( $zof_stage['dates'] ); ?></span>
						<span class="stage-text"><?php echo esc_html( $zof_stage['text'] ); ?></span>
						<?php if ( 'current' === $zof_stage['state'] ) : ?>
							<span class="stage-badge">Идёт сейчас</span>
						<?php elseif ( 'done' === $zof_stage['state'] ) : ?>
							<span class="stage-badge">Завершён</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
<?php endif; ?>

<?php if ( $zof_directions ) : ?>
	<section class="section section-tint" id="directions" aria-labelledby="directionsTitle">
		<div class="wrap">
			<div class="section-head">
				<p class="section-kicker">Участие</p>
				<h2 class="section-title" id="directionsTitle"><?php echo esc_html( zof_mod( 'directions_title' ) ); ?></h2>
			</div>
			<div class="directions directions-<?php echo esc_attr( (string) min( 3, count( $zof_directions ) ) ); ?>">
				<?php foreach ( $zof_directions as $zof_i => $zof_page ) : ?>
					<?php $zof_count = zof_count_nominations( $zof_page ); ?>
					<a class="direction" href="<?php echo esc_url( get_permalink( $zof_page ) ); ?>">
						<span class="direction-num" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $zof_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<?php if ( has_post_thumbnail( $zof_page ) ) : ?>
							<?php echo get_the_post_thumbnail( $zof_page, 'zof-card', array( 'class' => 'direction-img', 'loading' => 'lazy', 'alt' => '' ) ); ?>
						<?php endif; ?>
						<span class="direction-title"><?php echo esc_html( get_the_title( $zof_page ) ); ?></span>
						<?php $zof_summary = zof_direction_summary( $zof_page ); ?>
						<?php if ( $zof_summary ) : ?>
							<span class="direction-text"><?php echo esc_html( $zof_summary ); ?></span>
						<?php endif; ?>
						<span class="direction-foot">
							<?php if ( $zof_count ) : ?>
								<span class="direction-count"><?php echo esc_html( $zof_count . ' ' . zof_plural( $zof_count, 'номинация', 'номинации', 'номинаций' ) ); ?></span>
							<?php endif; ?>
							<span class="direction-go">Подробнее<?php echo zof_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( 'page' === get_option( 'show_on_front' ) && have_posts() ) : ?>
	<?php
	while ( have_posts() ) :
		the_post();
		if ( '' === trim( get_the_content() ) ) {
			continue;
		}
		?>
		<section class="section" id="about" aria-labelledby="aboutTitle">
			<div class="wrap">
				<div class="section-head">
					<p class="section-kicker">Форум</p>
					<h2 class="section-title" id="aboutTitle"><?php echo esc_html( zof_mod( 'about_title' ) ); ?></h2>
				</div>
				<div class="about-card entry-content">
					<?php the_content(); ?>
				</div>
			</div>
		</section>
	<?php endwhile; ?>
<?php elseif ( have_posts() ) : ?>
	<section class="section" aria-labelledby="newsTitle">
		<div class="wrap">
			<div class="section-head">
				<p class="section-kicker">Новости</p>
				<h2 class="section-title" id="newsTitle">Последние публикации</h2>
			</div>
			<div class="posts-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card' );
				endwhile;
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( $zof_cta_url && ! $zof_is_past ) : ?>
	<section class="cta-band">
		<?php echo zof_ribbons_svg( 'ribbons-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="wrap cta-inner">
			<div>
				<h2 class="cta-title">Станьте участником форума</h2>
				<?php if ( $zof_current ) : ?>
					<p class="cta-text"><?php echo esc_html( $zof_current['text'] . ': ' . $zof_current['dates'] ); ?></p>
				<?php endif; ?>
			</div>
			<a class="btn btn-light" href="<?php echo esc_url( $zof_cta_url ); ?>"><?php echo esc_html( zof_mod( 'cta_label' ) ); ?><?php echo zof_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
