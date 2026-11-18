<?php
declare(strict_types=1);

function page_start(string $title, ?array $admin = null, string $active = ''): void
{
    $app = (string) cfg('app_name');
    $css = url('assets/app.css') . '?v=' . (string) (@filemtime(CERT_PUBLIC . '/assets/app.css') ?: 1);
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . h($title) . ' — ' . h($app) . '</title>'
        . '<link rel="stylesheet" href="' . h($css) . '">'
        . '<style>' . font_faces_css() . '</style>'
        . '</head><body class="' . ($admin ? 'is-admin' : 'is-public') . '">';
    if ($admin) {
        $nav = [
            'templates' => ['admin/index.php', 'Шаблоны'],
            'users' => ['admin/users.php', 'Администраторы'],
            'verify' => ['verify.php', 'Проверка'],
        ];
        echo '<header class="topbar"><a class="brand" href="' . h(url('admin/index.php')) . '"><span class="brand-mark">С</span>' . h($app) . '</a><nav>';
        foreach ($nav as $key => [$href, $label]) {
            echo '<a href="' . h(url($href)) . '"' . ($key === $active ? ' class="is-active"' : '') . '>' . h($label) . '</a>';
        }
        echo '</nav><div class="topbar-user">' . h($admin['login'])
            . ' <form method="post" action="' . h(url('admin/logout.php')) . '">' . csrf_field()
            . '<button type="submit" class="link">Выйти</button></form></div></header>';
    }
    echo '<main class="' . ($admin ? 'page' : 'page page--narrow') . '">';
    $f = flash();
    if ($f) {
        echo '<div class="flash flash--' . h($f['type']) . '">' . h($f['text']) . '</div>';
    }
}

function page_end(): void
{
    echo '</main></body></html>';
}

function template_tabs(array $tpl, string $active): void
{
    $tabs = [
        'design' => ['admin/template.php?id=' . $tpl['id'], 'Макет'],
        'links' => ['admin/links.php?template=' . $tpl['id'], 'Ссылки для слушателей'],
        'bulk' => ['admin/bulk.php?template=' . $tpl['id'], 'Выдать по списку'],
        'issued' => ['admin/issued.php?template=' . $tpl['id'], 'Реестр выданных'],
    ];
    echo '<div class="page-head"><div><a class="small" href="' . h(url('admin/index.php')) . '">← Все шаблоны</a>'
        . '<h1>' . h($tpl['title']) . '</h1></div></div><div class="tabs">';
    foreach ($tabs as $key => [$href, $label]) {
        echo '<a href="' . h(url($href)) . '"' . ($key === $active ? ' class="is-active"' : '') . '>' . h($label) . '</a>';
    }
    echo '</div>';
}

function font_faces_css(): string
{
    $css = '';
    foreach (cert_fonts() as $font) {
        $css .= '@font-face{font-family:' . $font['family'] . ';src:url("' . h(url('assets/fonts/' . $font['file'])) . '") format("truetype");'
            . 'font-weight:' . $font['weight'] . ';font-style:' . $font['style'] . ';font-display:swap}';
    }

    return $css;
}
