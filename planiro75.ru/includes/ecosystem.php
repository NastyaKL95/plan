<?php
declare(strict_types=1);

/**
 * Сервисы экосистемы ИРО Забайкальского края.
 * Общий список для стартовой страницы (ecosystem.php) и меню «Экосистема» в шапке Вектора.
 * У «Вектора» url пустой: ссылка строится через planner_url(), чтобы работать и на /public/.
 *
 * @return list<array{key:string,name:string,short:string,domain:string,url:string,color:string,icon:string,desc:string,tags:list<string>,groups:string,internal?:bool}>
 */
function planner_ecosystem_services(): array
{
    return [
        [
            'key' => 'vector',
            'name' => 'Вектор',
            'short' => 'Вектор',
            'domain' => 'planiro75.ru',
            'url' => '',
            'color' => '#2fc06e',
            'icon' => '<path d="M5 19L18 6"/><path d="M9.5 6H18v8.5"/><circle cx="5" cy="19" r="1.6" fill="currentColor"/>',
            'desc' => 'Ваши задачи и проекты отдела, «Мой план» на неделю, индивидуальный план и отчёт преподавателя, документы института и уведомления в MAX.',
            'tags' => ['Задачи', 'План и отчёт', 'Документы'],
            'groups' => 'plan',
            'internal' => true,
        ],
        [
            'key' => 'af',
            'name' => 'Аудиторный фонд',
            'short' => 'Аудитории',
            'domain' => 'af.zabedu.ru',
            'url' => 'https://af.zabedu.ru/',
            'color' => '#14b8a6',
            'icon' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-5h6v5"/><path d="M9 10h.01M15 10h.01"/>',
            'desc' => 'Перед семинаром, курсами или совещанием проверьте свободные аудитории и расписание занятий института.',
            'tags' => ['Аудитории', 'Расписание'],
            'groups' => 'plan events',
        ],
        [
            'key' => 'vitrina',
            'name' => 'Витрина мероприятий',
            'short' => 'Витрина',
            'domain' => 'eventt.zabedu.ru',
            'url' => 'https://eventt.zabedu.ru/',
            'color' => '#1d7fe0',
            'icon' => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/><path d="M8 14h3M8 17.5h7"/>',
            'desc' => 'Календарь мероприятий #educationlife: анонсы семинаров, конференций, конкурсов и курсов института.',
            'tags' => ['Анонсы', 'Мероприятия'],
            'groups' => 'events info',
        ],
        [
            'key' => 'konkurs',
            'name' => 'Конкурсы и проекты',
            'short' => 'Конкурсы',
            'domain' => 'konkurs.zabedu.ru',
            'url' => 'https://konkurs.zabedu.ru/',
            'color' => '#ef4f6b',
            'icon' => '<path d="M8 21h8M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>',
            'desc' => 'Сопровождение конкурсов и социально значимых проектов: объявления, приём и рассмотрение заявок участников.',
            'tags' => ['Конкурсы', 'Заявки', 'Экспертиза'],
            'groups' => 'events',
        ],
        [
            'key' => 'do75',
            'name' => 'Дистанционное обучение',
            'short' => 'Обучение',
            'domain' => 'do75.zabedu.ru',
            'url' => 'https://do75.zabedu.ru/',
            'color' => '#8b5cf6',
            'icon' => '<path d="M22 9L12 4 2 9l10 5 10-5z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/><path d="M22 9v6"/>',
            'desc' => 'Ведение курсов повышения квалификации: материалы, задания и тесты, работа со слушателями в личных кабинетах.',
            'tags' => ['Курсы', 'Слушатели', 'Онлайн'],
            'groups' => 'learn',
        ],
        [
            'key' => 'metodnav',
            'name' => 'Методический навигатор',
            'short' => 'Навигатор',
            'domain' => 'metodnav.zabedu.ru',
            'url' => 'https://metodnav.zabedu.ru/',
            'color' => '#f59e0b',
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5z"/>',
            'desc' => 'Методические материалы и рекомендации для педагогов края: публикуйте разработки и направляйте к ним слушателей.',
            'tags' => ['Методика', 'Материалы'],
            'groups' => 'learn',
        ],
        [
            'key' => 'portal',
            'name' => 'Образовательный портал',
            'short' => 'Портал',
            'domain' => 'zabedu.ru',
            'url' => 'https://zabedu.ru/',
            'color' => '#2b8cff',
            'icon' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            'desc' => 'Новости института и системы образования края, контакты организаций по районам, почта и общие сервисы.',
            'tags' => ['Новости', 'Организации', 'Контакты'],
            'groups' => 'info',
        ],
    ];
}

function planner_ecosystem_service_url(array $service): string
{
    return !empty($service['internal']) ? planner_url('/index.php') : (string) $service['url'];
}

/** Адрес стартовой страницы экосистемы (корень сайта). */
function planner_ecosystem_url(): string
{
    return planner_url('/');
}

/** SVG-знак «Вектора» (тот же рисунок, что в favicon.svg). */
function planner_vector_mark_svg(int $size = 36, string $class = ''): string
{
    static $n = 0;
    $id = 'vmark' . (++$n);
    $cls = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

    return '<svg' . $cls . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="#1f9a55"/><stop offset="1" stop-color="#2fc06e"/></linearGradient></defs>'
        . '<rect width="32" height="32" rx="9" fill="url(#' . $id . ')"/>'
        . '<path d="M10 22 21.5 10.5" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M13.5 10h8.5v8.5" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="9.5" cy="22.5" r="2.4" fill="#fff"/>'
        . '</svg>';
}

/** SVG-знак экосистемы ИРО (как в шапке стартовой страницы). */
function planner_ecosystem_mark_svg(int $size = 36, string $class = ''): string
{
    static $n = 0;
    $id = 'emark' . (++$n);
    $cls = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

    return '<svg' . $cls . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 36 36" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1f9a55"/><stop offset="1" stop-color="#2b8cff"/></linearGradient></defs>'
        . '<rect width="36" height="36" rx="10" fill="url(#' . $id . ')"/>'
        . '<path d="M6 26l7-10 5 6 4-5 8 9z" fill="#fff" opacity=".95"/>'
        . '<circle cx="25" cy="11" r="3" fill="#fff"/>'
        . '</svg>';
}

/** Выпадающее меню «Экосистема ИРО» для шапки Вектора. */
function planner_ecosystem_launcher_html(): string
{
    $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $items = '';
    foreach (planner_ecosystem_services() as $s) {
        $internal = !empty($s['internal']);
        $attrs = $internal ? ' aria-current="page"' : ' target="_blank" rel="noopener"';
        $items .= '<a class="bx-eco-item' . ($internal ? ' is-current' : '') . '" href="' . $h(planner_ecosystem_service_url($s)) . '" role="menuitem"' . $attrs
            . ' style="--c:' . $h($s['color']) . '">'
            . '<span class="bx-eco-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $s['icon'] . '</svg></span>'
            . '<span class="bx-eco-name">' . $h($s['short']) . '</span>'
            . '<span class="bx-eco-dom">' . $h($internal ? 'вы здесь' : $s['domain']) . '</span>'
            . '</a>';
    }

    return '<div class="bx-eco" id="bxEco">'
        . '<button type="button" class="bx-eco-btn" id="bxEcoBtn" aria-haspopup="true" aria-expanded="false" aria-controls="bxEcoPanel" title="Экосистема ИРО">'
        . '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></svg>'
        . '<span class="bx-eco-btn-label">Экосистема</span>'
        . '</button>'
        . '<div class="bx-eco-panel" id="bxEcoPanel" role="menu" aria-label="Сервисы экосистемы ИРО" hidden>'
        . '<a class="bx-eco-head" href="' . $h(planner_ecosystem_url()) . '" role="menuitem">'
        . planner_ecosystem_mark_svg(34)
        . '<span><b>Экосистема ИРО</b><small>Забайкальского края</small></span>'
        . '</a>'
        . '<div class="bx-eco-grid">' . $items . '</div>'
        . '<a class="bx-eco-all" href="' . $h(planner_ecosystem_url()) . '" role="menuitem">'
        . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>'
        . 'На страницу экосистемы</a>'
        . '</div>'
        . '</div>';
}
