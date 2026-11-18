<?php
/**
 * Настройки форума в «Внешний вид → Настроить → Форум».
 *
 * @package zof
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zof_defaults() {
	return array(
		'hero_eyebrow'     => 'Забайкальский образовательный форум',
		'hero_title'       => 'От смыслов к изменениям через образовательные инновации',
		'hero_lead'        => 'Трансляция лучших управленческих и педагогических практик и проектов, демонстрация достижений системы образования Забайкальского края.',
		'event_datetime'   => '2026-12-17T10:00',
		'event_place'      => 'г. Чита',
		'event_note'       => 'Заключительный очный этап',
		'show_countdown'   => true,
		'cta_label'        => 'Подать заявку',
		'cta_url'          => '',
		'doc_label'        => 'Приказ Минобразования Забайкальского края от 13.11.2026 № 811',
		'doc_url'          => '',
		'show_stages'      => true,
		'stages_title'     => 'Сроки проведения',
		'stage_1_dates'    => '16 ноября — 1 декабря',
		'stage_1_end'      => '2026-12-01',
		'stage_1_text'     => 'Приём заявок и конкурсных материалов',
		'stage_2_dates'    => '1 — 10 декабря',
		'stage_2_end'      => '2026-12-10',
		'stage_2_text'     => 'Отборочный (заочный) этап: техническая и содержательная экспертиза конкурсных материалов',
		'stage_3_dates'    => '11 декабря',
		'stage_3_end'      => '2026-12-11',
		'stage_3_text'     => 'Список участников очного этапа на сайте ГУ ДПО «ИРО Забайкальского края»',
		'stage_4_dates'    => '17 декабря',
		'stage_4_end'      => '2026-12-17',
		'stage_4_text'     => 'Заключительный (очный) этап Форума',
		'directions_title' => 'Конкурсы форума',
		'about_title'      => 'О форуме',
		'organizers'       => "Министерство образования Забайкальского края\nГУ ДПО «ИРО Забайкальского края»",
		'contact_email'    => '',
		'contact_phone'    => '',
		'contact_address'  => '',
	);
}

function zof_mod( $key ) {
	$defaults = zof_defaults();
	return get_theme_mod( $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );
}

function zof_sanitize_checkbox( $value ) {
	return (bool) $value;
}

function zof_sanitize_date( $value ) {
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? $value : '';
}

function zof_sanitize_datetime( $value ) {
	$value = str_replace( ' ', 'T', trim( (string) $value ) );
	return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';
}

function zof_customize_register( $wp_customize ) {
	$d = zof_defaults();

	$wp_customize->add_panel(
		'zof_panel',
		array(
			'title'    => 'Форум',
			'priority' => 25,
		)
	);

	$sections = array(
		'zof_hero'       => 'Афиша на главной',
		'zof_stages'     => 'Сроки проведения',
		'zof_directions' => 'Конкурсы',
		'zof_logos'      => 'Логотипы партнёров',
		'zof_contacts'   => 'Организаторы и контакты',
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section( $id, array( 'title' => $title, 'panel' => 'zof_panel' ) );
	}

	$add = function ( $key, $section, $label, $type = 'text', $sanitize = 'sanitize_text_field', $extra = array() ) use ( $wp_customize, $d ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => isset( $d[ $key ] ) ? $d[ $key ] : '',
				'sanitize_callback' => $sanitize,
			)
		);
		$wp_customize->add_control( $key, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $type ), $extra ) );
	};

	$add( 'hero_eyebrow', 'zof_hero', 'Надзаголовок' );
	$add( 'hero_title', 'zof_hero', 'Тема форума', 'textarea', 'sanitize_textarea_field' );
	$add( 'hero_lead', 'zof_hero', 'Короткое описание', 'textarea', 'sanitize_textarea_field' );
	$add( 'event_datetime', 'zof_hero', 'Дата и время очного этапа', 'datetime-local', 'zof_sanitize_datetime', array( 'description' => 'По часовому поясу сайта (Настройки → Общие). От этой даты считается обратный отсчёт.' ) );
	$add( 'event_place', 'zof_hero', 'Место проведения' );
	$add( 'event_note', 'zof_hero', 'Подпись к дате' );
	$add( 'show_countdown', 'zof_hero', 'Показывать обратный отсчёт', 'checkbox', 'zof_sanitize_checkbox' );
	$add( 'cta_label', 'zof_hero', 'Текст главной кнопки' );
	$add( 'cta_url', 'zof_hero', 'Ссылка главной кнопки', 'url', 'esc_url_raw', array( 'description' => 'Например, форма заявки. Если пусто, кнопка ведёт к списку конкурсов.' ) );
	$add( 'doc_label', 'zof_hero', 'Название документа' );
	$add( 'doc_url', 'zof_hero', 'Ссылка на документ (приказ, положение)', 'url', 'esc_url_raw' );

	$add( 'show_stages', 'zof_stages', 'Показывать сроки на главной', 'checkbox', 'zof_sanitize_checkbox' );
	$add( 'stages_title', 'zof_stages', 'Заголовок блока' );
	for ( $i = 1; $i <= 4; $i++ ) {
		$add( "stage_{$i}_dates", 'zof_stages', "Этап {$i}: даты (как показывать)" );
		$add( "stage_{$i}_end", 'zof_stages', "Этап {$i}: последний день", 'date', 'zof_sanitize_date', array( 'description' => 'Нужен, чтобы отметить текущий этап.' ) );
		$add( "stage_{$i}_text", 'zof_stages', "Этап {$i}: описание", 'textarea', 'sanitize_textarea_field' );
	}

	$add( 'directions_title', 'zof_directions', 'Заголовок блока' );
	for ( $i = 1; $i <= 4; $i++ ) {
		$add( "direction_{$i}_page", 'zof_directions', "Страница конкурса {$i}", 'dropdown-pages', 'absint', array( 'description' => 1 === $i ? 'Если ничего не выбрано, показываются все страницы верхнего уровня, кроме главной.' : '' ) );
	}
	$add( 'about_title', 'zof_directions', 'Заголовок текста главной страницы' );

	for ( $i = 1; $i <= 5; $i++ ) {
		$wp_customize->add_setting( "logo_{$i}", array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				"logo_{$i}",
				array(
					'label'     => "Логотип {$i}",
					'section'   => 'zof_logos',
					'mime_type' => 'image',
				)
			)
		);
		$add( "logo_{$i}_url", 'zof_logos', "Ссылка для логотипа {$i}", 'url', 'esc_url_raw' );
	}

	$add( 'organizers', 'zof_contacts', 'Организаторы (каждый с новой строки)', 'textarea', 'sanitize_textarea_field' );
	$add( 'contact_email', 'zof_contacts', 'Электронная почта', 'email', 'sanitize_email' );
	$add( 'contact_phone', 'zof_contacts', 'Телефон' );
	$add( 'contact_address', 'zof_contacts', 'Адрес', 'textarea', 'sanitize_textarea_field' );
}
add_action( 'customize_register', 'zof_customize_register' );
