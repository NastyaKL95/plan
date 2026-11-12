#!/usr/bin/env python3
"""Word + PowerPoint для самостоятельного курса 12 часов (без муниципального комплекта)."""
from __future__ import annotations

from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Cm, Pt
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt as PptPt

OUT = Path(__file__).resolve().parent
ACCENT = RGBColor(0x1F, 0x4E, 0x79)
INK = RGBColor(0x1C, 0x1A, 0x17)
MUTED = RGBColor(0x5C, 0x57, 0x4F)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)


def set_doc_defaults(doc: Document) -> None:
    style = doc.styles["Normal"]
    style.font.name = "Times New Roman"
    style.font.size = Pt(12)
    for s in doc.sections:
        s.top_margin = Cm(2)
        s.bottom_margin = Cm(2)
        s.left_margin = Cm(2.5)
        s.right_margin = Cm(2)


def title(doc, text):
    p = doc.add_heading(text, 0)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER


def h(doc, text, level=1):
    doc.add_heading(text, level)


def p(doc, text, bold=False):
    para = doc.add_paragraph()
    r = para.add_run(text)
    r.bold = bold
    r.font.name = "Times New Roman"
    r.font.size = Pt(12)


def bullets(doc, items):
    for it in items:
        para = doc.add_paragraph(it, style="List Bullet")
        for r in para.runs:
            r.font.name = "Times New Roman"
            r.font.size = Pt(12)


def table(doc, headers, rows):
    t = doc.add_table(rows=1 + len(rows), cols=len(headers))
    t.style = "Table Grid"
    for i, x in enumerate(headers):
        t.rows[0].cells[i].text = x
    for ri, row in enumerate(rows):
        for ci, val in enumerate(row):
            t.rows[ri + 1].cells[ci].text = val
    doc.add_paragraph()


def build_temy() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Курс 12 часов: подробные темы и разборы")
    p(doc, "Самостоятельный курс подготовки к олимпиаде (9–11 класс). Не использует задачи муниципального комплекта.")

    h(doc, "Карта курса")
    table(
        doc,
        ["Блок", "Часы", "Тема", "Учебный разбор"],
        [
            ["1", "0–2", "Формулы и упаковка", "Лифт, полки"],
            ["2", "2–4", "Цифры числа", "Сумма S, произведение N"],
            ["3", "4–6", "Массивы и частоты", "Мода, серии"],
            ["4", "6–8", "Подсчёт на сетке", "Прямоугольники, квадраты"],
            ["5", "8–10", "Префиксы и два указателя", "Суммы отрезков, окно ≤ S"],
            ["6", "10–12", "Мини-тур", "4 новые задачи курса"],
        ],
    )

    # 1
    h(doc, "Тема 1. Формулы и упаковка")
    h(doc, "О чём", 2)
    p(doc, "Если n огромное, цикл не успеет. Нужна формула. Частый сюжет — разложить n объектов по группам вместимости k.")
    h(doc, "Зачем", 2)
    p(doc, "На олимпиаде ограничения сразу подсказывают сложность. Умение видеть ⌈n/k⌉ экономит время и баллы.")
    h(doc, "Как", 2)
    bullets(doc, [
        "Посмотреть ограничения.",
        "Если ответ — число полных групп: ⌈n/k⌉ = (n+k−1)//k.",
        "Проверить края: n=k, n=1, большие значения.",
    ])
    h(doc, "Разбор: «Лифт»", 2)
    p(doc, "n человек, вместимость k. Рейсов = ⌈n/k⌉. Примеры: 10 3 → 4; 10 5 → 2.")
    h(doc, "Разбор: «Полки»", 2)
    p(doc, "n книг, на полку ≤ m. Снова ⌈n/m⌉ — тот же шаблон в другой истории.")

    # 2
    h(doc, "Тема 2. Цифры числа")
    h(doc, "О чём", 2)
    p(doc, "Разбираем число на цифры или собираем новое число с условием на сумму/произведение цифр.")
    h(doc, "Зачем", 2)
    p(doc, "Тренирует жадность: правильный выбор цифр даёт минимальное или максимальное число.")
    h(doc, "Как (сумма = S)", 2)
    bullets(doc, [
        "Минимальное число: кладём девятки справа, остаток — слева.",
        "Примеры: S=10 → 19; S=20 → 299.",
    ])
    h(doc, "Как (произведение = N)", 2)
    bullets(doc, [
        "Делим N на цифры от 9 к 2.",
        "Если остаток ≠ 1 → −1.",
        "Цифры по возрастанию → минимум. Пример: 36 → 49.",
    ])

    # 3
    h(doc, "Тема 3. Массивы и частоты")
    h(doc, "О чём", 2)
    p(doc, "Подсчёт частот, поиск самого частого значения, длины серий подряд равных элементов.")
    h(doc, "Зачем", 2)
    p(doc, "При n до 1e5 нужен линейный проход, а не двойной цикл.")
    h(doc, "Разбор: мода", 2)
    p(doc, "Counter; при равной частоте берём меньшее значение. Пример: 1 2 2 3 3 → 2 2.")
    h(doc, "Разбор: серия", 2)
    p(doc, "Один проход с текущей длиной. Пример: 5 5 5 4 4 → 3.")

    # 4
    h(doc, "Тема 4. Подсчёт на сетке")
    h(doc, "О чём", 2)
    p(doc, "Сколько прямоугольников/квадратов на поле n×m клеток — считаем формулой.")
    h(doc, "Как", 2)
    bullets(doc, [
        "Прямоугольники: n(n+1)/2 · m(m+1)/2.",
        "Квадраты: сумма по s=1..min (n−s+1)(m−s+1).",
        "Проверка на 1×1, 2×2 обязательна.",
    ])
    h(doc, "Разбор", 2)
    p(doc, "2×2 прямоугольников: 9. Квадратов: 5. Диагональ клеток: n+m−gcd(n,m).")

    # 5
    h(doc, "Тема 5. Префикс-суммы и два указателя")
    h(doc, "О чём", 2)
    p(doc, "Префиксы отвечают на суммы отрезков за O(1). Два указателя двигают окно и поддерживают сумму/длину.")
    h(doc, "Разбор: суммы запросов", 2)
    p(doc, "pref[0]=0; pref[i]=a[0]+…+a[i−1]; сумма [l..r] = pref[r]−pref[l−1] (1-индексация в запросе).")
    h(doc, "Разбор: длина с суммой ≤ S", 2)
    p(doc, "Неотрицательный массив: двигаем right, пока сумма > S — двигаем left. Пример: 1 2 1 3 и S=5 → длина 3.")

    # 6
    h(doc, "Тема 6. Мини-тур (свои задачи курса)")
    p(doc, "90 минут на 4 задачи + 30 минут разбора.")
    table(
        doc,
        ["Код", "Название", "Тема", "Суть"],
        [
            ["T1", "Рейсы", "формулы", "⌈n/k⌉"],
            ["T2", "Код замка", "цифры", "мин. число с произведением N"],
            ["T3", "Мода массива", "частоты", "макс. частота, ничья → меньшее"],
            ["T4", "Площадки", "сетка", "число квадратов на n×m"],
        ],
    )
    p(doc, "После тура быстрые берут дополнительные задачи из dopolnitelno/.")

    path = OUT / "Temy_i_razbory.docx"
    doc.save(path)
    return path


def build_programma() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Программа курса 12 часов (быстрая группа)")
    p(doc, "Теорию короче. Основная нагрузка — реальные задачи ВсОШ и Codeforces.")
    h(doc, "Структура блока (2 часа)")
    bullets(doc, [
        "0–12 мин — тема коротко",
        "12–30 мин — разбор + 1 задача практики",
        "30–55 мин — praktika/",
        "55–100 мин — realnye/ (главное)",
        "100–120 мин — dopolnitelno/ или вторая реальная + сверка",
    ])
    h(doc, "Расписание + реальные задачи")
    table(
        doc,
        ["Блок", "Тема", "Реальные"],
        [
            ["1", "Формулы", "Самолёт Мск 2022; Речные прогулки Мск 2024; Кино Влд 2019"],
            ["2", "Цифры", "Престижный номер Мск 2022; Пароль Влд 2019"],
            ["3", "Массивы", "Ожерелье Влд 2019; Порядок во всём Мск 2024"],
            ["4", "Сетка", "Треугольники Мск 2024"],
            ["5", "Серии / 2 указателя", "CF 580A; CF 6C; Майки и носки Мск 2022"],
            ["6", "Мини-тур", "T1 + R4 + R1 + R8"],
        ],
    )
    path = OUT / "Programma.docx"
    doc.save(path)
    return path


def build_realnye() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Реальные олимпиадные задачи")
    p(doc, "Краткие условия и идеи. Полные тексты — в официальных PDF (ссылки в realnye/*.md).")

    h(doc, "Тема 1. Формулы")
    p(doc, "R1. Самолёт — ВсОШ Москва 2022/23. n кресел, края ≤3, между проходами ≤4. Пример: 10→2. Формула max(1, 1+(n−3)//4).", bold=False)
    p(doc, "R2. Речные прогулки — ВсОШ Москва 2024/25. Выбрать пристань i, минимизируя |(i−1)a−(n−i)b|. Пример: 8,7,3 → 3.")
    p(doc, "R3. Кино — ВсОШ Владивосток 2019/20. Ожидание сеансов с периодами 15/10/5. Пример → 18.")

    h(doc, "Тема 2. Цифры")
    p(doc, "R4. Престижный номер — ВсОШ Москва 2022/23. Мин. число с произведением цифр N. 70→257, 101→−1.")
    p(doc, "R5. Пароль — ВсОШ Владивосток 2019/20. То же, но длина ≥2; при N<10 ответ 10+N (1→11).")

    h(doc, "Тема 3. Массивы")
    p(doc, "R6. Ожерелье и браслеты — ВсОШ Владивосток 2019/20. Круг из 0/1, вырезать куски из k единиц.")
    p(doc, "R7. Порядок во всём — ВсОШ Москва 2024/25. Дописывать цифры в конец, минимизировать последнее число.")

    h(doc, "Тема 4. Сетка")
    p(doc, "R8. Треугольники — ВсОШ Москва 2024/25. Формула 8nm+2(n−1)m+2n(m−1)+4(n−1)(m−1). Примеры: 1×1→8, 3×5→196.")

    h(doc, "Тема 5. Серии и два указателя")
    p(doc, "R10. Codeforces 580A — макс. неубывающая серия.")
    p(doc, "R11. Codeforces 6C — Алиса и Боб едят с двух концов.")
    p(doc, "R12. Майки и носки — ВсОШ Москва 2022/23, принцип Дирихле.")

    h(doc, "Ссылки на PDF")
    bullets(doc, [
        "Москва 2022: vos.olimpiada.ru/.../tasks-iikt-9-11-mun-msk-22-23.pdf",
        "Москва 2024: vos.olimpiada.ru/.../tasks-iikt-9-11-mun-msk-24-25.pdf",
        "Владивосток 2019: tasks.olimpiada.ru/.../tasks-iikt-9-11-mun-vlad-19-0.pdf",
        "CF 580A: codeforces.com/problemset/problem/580/A",
        "CF 6C: codeforces.com/problemset/problem/6/C",
    ])
    path = OUT / "Realnye_olimpiadnye.docx"
    doc.save(path)
    return path


def build_praktika() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Практика и дополнительные задачи")

    h(doc, "Практика по темам")
    p(doc, "Тема 1. P1A Страницы ⌈n/k⌉; P1B Коробки; P1C Столбы p+1.")
    p(doc, "Тема 2. P2A Сумма цифр; P2B Мин. число с суммой S; P2C Произведение N.")
    p(doc, "Тема 3. P3A Мода; P3B Серия; P3C Число различных.")
    p(doc, "Тема 4. P4A Прямоугольники; P4B Квадраты; P4C Диагональ n+m−gcd.")
    p(doc, "Тема 5. P5A Запросы сумм; P5B Окно ≤S; P5C Число подотрезков ≤S.")

    h(doc, "Мини-тур")
    bullets(doc, [
        "T1 Рейсы — ⌈n/k⌉, n до 1e18",
        "T2 Код замка — мин. число с произведением цифр N",
        "T3 Мода массива — частота и значение",
        "T4 Площадки — число квадратов на сетке",
    ])

    h(doc, "Дополнительные задачи (те же темы)")
    bullets(doc, [
        "D1: две вместимости коробок; дни с растущей нормой",
        "D2: макс. перестановка цифр; число длины L с суммой S",
        "D3: вторая по частоте; сжатие серий",
        "D4: квадраты 45°; прямоугольники площади P",
        "D5: отрезки с суммой ≥X; окно длины K",
        "E после тура: смешанная упаковка; сумма цифр до m; мода на префиксах",
    ])
    path = OUT / "Praktika_i_dopolnitelno.docx"
    doc.save(path)
    return path


def build_otvety() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Ответы")
    p(doc, "Практика 1: ⌈n/k⌉; p+1.")
    p(doc, "Практика 2: сумма цифр; 19/299/9; 49/1/−1.")
    p(doc, "Практика 3: 2 2; 3; размер set.")
    p(doc, "Практика 4: 1 и 18; 5 и 8; 2 и 4.")
    p(doc, "Практика 5: pref[r]−pref[l−1]; длина 3; 4 подотрезка.")
    h(doc, "Реальные")
    p(doc, "R1: max(1,1+(n−3)//4), 10→2.  R2: пример →3.  R3: пример →18.")
    p(doc, "R4: 70→257, 101→−1.  R5: 20→45, 1→11.")
    p(doc, "R6: примеры →1 и →2.  R7: 7; 13; 100.")
    p(doc, "R8: 8nm+2(n−1)m+2n(m−1)+4(n−1)(m−1); 1×1→8; 3×5→196.")
    p(doc, "R10: пример →3.  Тур: T1 + R4 + R1 + R8.")
    path = OUT / "Otvety.docx"
    doc.save(path)
    return path


def build_shpargalka() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    title(doc, "Шпаргалка курса")
    h(doc, "Формулы")
    bullets(doc, ["⌈n/k⌉ = (n+k−1)//k", "Большое n ⇒ не цикл"])
    h(doc, "Цифры")
    bullets(doc, ["Сумма S: девятки справа", "Произведение N: делители 9..2, иначе −1"])
    h(doc, "Массивы")
    bullets(doc, ["Counter + ничья", "Серии подряд одним проходом"])
    h(doc, "Сетка")
    bullets(doc, ["Прямоугольники: n(n+1)/2·m(m+1)/2", "Квадраты: сумма (n−s+1)(m−s+1)"])
    h(doc, "Префиксы / два указателя")
    bullets(doc, ["Сумма [l..r] через pref", "Окно на неотрицательном массиве"])
    path = OUT / "Shpargalka.docx"
    doc.save(path)
    return path


def _blank(prs):
    return prs.slides.add_slide(prs.slide_layouts[6])


def _bar(slide):
    sh = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0), Inches(0), Inches(13.333), Inches(0.28))
    sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = ACCENT


def _text(slide, l, t, w, h, text, size=28, bold=False, color=INK, align=PP_ALIGN.LEFT):
    box = slide.shapes.add_textbox(l, t, w, h)
    tf = box.text_frame
    tf.word_wrap = True
    para = tf.paragraphs[0]
    para.alignment = align
    run = para.add_run()
    run.text = text
    run.font.size = PptPt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    run.font.name = "Calibri"


def _bullets(slide, l, t, w, h, lines, size=20):
    box = slide.shapes.add_textbox(l, t, w, h)
    tf = box.text_frame
    tf.word_wrap = True
    for i, line in enumerate(lines):
        para = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        para.text = "•  " + line
        para.font.size = PptPt(size)
        para.font.color.rgb = INK
        para.font.name = "Calibri"
        para.space_after = PptPt(8)


def title_slide(prs, title_s, sub):
    s = _blank(prs)
    _bar(s)
    _text(s, Inches(0.7), Inches(2.3), Inches(12), Inches(1.4), title_s, 38, True, ACCENT)
    _text(s, Inches(0.7), Inches(4.0), Inches(12), Inches(1.4), sub, 20, False, MUTED)


def content(prs, title_s, lines, foot=None, size=20):
    s = _blank(prs)
    _bar(s)
    _text(s, Inches(0.6), Inches(0.45), Inches(12), Inches(0.7), title_s, 30, True, ACCENT)
    _bullets(s, Inches(0.75), Inches(1.3), Inches(11.7), Inches(5.3), lines, size)
    if foot:
        _text(s, Inches(0.75), Inches(6.85), Inches(11.7), Inches(0.4), foot, 15, False, MUTED)


def section(prs, num, name, one):
    s = _blank(prs)
    sh = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0), Inches(0), Inches(13.333), Inches(7.5))
    sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = ACCENT
    _text(s, Inches(0.8), Inches(2.1), Inches(11.5), Inches(0.6), f"Блок {num}", 22, True, WHITE)
    _text(s, Inches(0.8), Inches(2.9), Inches(11.5), Inches(1.2), name, 40, True, WHITE)
    _text(s, Inches(0.8), Inches(4.4), Inches(11.5), Inches(1.2), one, 22, False, RGBColor(0xD6, 0xE3, 0xF0))


def build_pptx() -> Path:
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)

    title_slide(prs, "Олимпиада по информатике · курс 12 часов", "Быстрая группа · реальные задачи ВсОШ · практика + доп.")
    content(prs, "Темп для быстрой группы", [
        "Теория 10–12 минут, без воды",
        "Сначала короткая практика, сразу реальные олимпиадные",
        "Кто закончил раньше — следующая реальная / CF",
        "Мини-тур: смесь учебных и задач ВсОШ",
    ])
    content(prs, "Карта 12 часов + реальные задачи", [
        "1. Формулы — Самолёт, Речные прогулки, Кино",
        "2. Цифры — Престижный номер, Пароль",
        "3. Массивы — Ожерелье, Порядок во всём",
        "4. Сетка — Треугольники (Москва 2024)",
        "5. Серии / 2 указателя — CF 580A, CF 6C, Майки",
        "6. Тур: T1 + R4 + R1 + R8",
    ])

    section(prs, "1", "Формулы и упаковка", "Большое n ⇒ считаем формулой, а не циклом")
    content(prs, "Тема 1 · О чём и зачем", [
        "Ограничение n до 1e9–1e18 запрещает медленный перебор",
        "Шаблон «разложить n по группам размера k»",
        "Ответ часто ⌈n/k⌉ = (n+k−1)//k",
        "Навык читать ограничения и выбирать сложность",
    ])
    content(prs, "Тема 1 · Разбор «Лифт»", [
        "n человек, вместимость k → число рейсов ⌈n/k⌉",
        "Пример: 10 и 3 → 4 рейса",
        "Тот же шаблон: книги на полки, страницы по дням, яблоки в коробки",
        "Практика P1 → затем реальные R1 Самолёт, R2 Речные прогулки, R3 Кино",
    ])

    section(prs, "2", "Цифры числа", "Сначала какие цифры, потом какой порядок")
    content(prs, "Тема 2 · О чём и зачем", [
        "Сумма цифр, произведение цифр, сборка min/max числа",
        "Жадность: правильный локальный выбор цифры",
        "Важно обработать невозможный случай (−1)",
    ])
    content(prs, "Тема 2 · Разборы", [
        "Сумма S: минимум = девятки справа, остаток слева (10→19, 20→299)",
        "Произведение N: делители от 9 к 2, иначе −1 (36→49)",
        "Практика P2 → реальные R4 Престижный номер, R5 Пароль",
    ])

    section(prs, "3", "Массивы и частоты", "Один проход вместо двойного цикла")
    content(prs, "Тема 3 · Разборы + реальные", [
        "Мода и серии — базовый навык",
        "Реальная R6: Ожерелье (круг, жадные куски единиц)",
        "Реальная R7: Порядок во всём (дописывание цифр)",
        "Практика P3 → сразу в realnye/03",
    ])

    section(prs, "4", "Подсчёт на сетке", "Тип фигуры × размер × позиции")
    content(prs, "Тема 4 · Разборы + реальные", [
        "Разминка: число прямоугольников / квадратов",
        "Реальная R8 Треугольники (Москва 2024)",
        "Формула: 8nm + 2(n−1)m + 2n(m−1) + 4(n−1)(m−1)",
        "Проверка: 1×1→8, 2×2→44, 3×5→196",
    ])

    section(prs, "5", "Серии и два указателя", "Окна и проходы с двух концов")
    content(prs, "Тема 5 · Реальные / CF", [
        "Префиксы и окно суммы ≤ S — разминка",
        "R10 CF 580A — максимальная неубывающая серия",
        "R11 CF 6C — два указателя с концов",
        "R12 Майки и носки — принцип Дирихле",
    ])

    content(prs, "Откуда брать полные условия", [
        "Папка realnye/ — краткие условия + идеи",
        "Официальные PDF ВсОШ на vos.olimpiada.ru / tasks.olimpiada.ru",
        "Codeforces: задачи 580A и 6C",
        "Word: Realnye_olimpiadnye.docx",
    ])

    section(prs, "6", "Мини-тур", "90 минут · учебные + реальные ВсОШ")
    content(prs, "Задачи тура (быстрая группа)", [
        "1) T1 Рейсы — разминка ⌈n/k⌉",
        "2) R4 Престижный номер — ВсОШ Москва 2022",
        "3) R1 Самолёт — ВсОШ Москва 2022",
        "4) R8 Треугольники — ВсОШ Москва 2024",
        "Кто сдал раньше: R7 / R10 / R11",
    ])
    content(prs, "Итог курса", [
        "Узнаёте шаблон за 1–2 минуты чтения условия",
        "Решаете и учебные, и реальные муниц. задачи",
        "Умеете проверять края и огромные ограничения",
    ], "kurs-olimpiada-12h/realnye/ · office/*.docx")

    title_slide(prs, "Удачи на олимпиаде!", "Realnye_olimpiadnye.docx · Prezentaciya_kurs_12h.pptx")

    path = OUT / "Prezentaciya_kurs_12h.pptx"
    prs.save(path)
    return path


def build_pptx_tur() -> Path:
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    title_slide(prs, "Мини-тур (быстрая группа)", "90 минут · учебная разминка + реальные ВсОШ")
    content(prs, "1. T1. Рейсы", ["⌈n/k⌉", "Разминка на 10–15 минут"])
    content(prs, "2. R4. Престижный номер", ["ВсОШ Москва 2022/23", "Произведение цифр = N → мин. число", "70→257; 101→−1"])
    content(prs, "3. R1. Самолёт", ["ВсОШ Москва 2022/23", "Края ≤3, середина ≤4", "10→2"])
    content(prs, "4. R8. Треугольники", ["ВсОШ Москва 2024/25", "Формула с 1×1 / 1×2 / 2×1 / 2×2", "1×1→8; 3×5→196"])
    content(prs, "Если сдали рано", ["R7 Порядок во всём", "R10 CF 580A", "R11 CF 6C"])
    path = OUT / "Prezentaciya_mini_tur.pptx"
    prs.save(path)
    return path


def main():
    paths = [
        build_temy(),
        build_programma(),
        build_praktika(),
        build_realnye(),
        build_otvety(),
        build_shpargalka(),
        build_pptx(),
        build_pptx_tur(),
    ]
    (OUT / "README.md").write_text(
        "# Office-материалы курса 12 часов (быстрая группа)\n\n"
        "| Файл | Назначение |\n|------|------------|\n"
        "| `Temy_i_razbory.docx` | Темы и учебные разборы |\n"
        "| `Realnye_olimpiadnye.docx` | Реальные задачи ВсОШ/CF |\n"
        "| `Programma.docx` | Ускоренная программа |\n"
        "| `Praktika_i_dopolnitelno.docx` | Практика + доп. |\n"
        "| `Otvety.docx` | Ответы |\n"
        "| `Shpargalka.docx` | Шпаргалка |\n"
        "| `Prezentaciya_kurs_12h.pptx` | Презентация курса |\n"
        "| `Prezentaciya_mini_tur.pptx` | Презентация мини-тура |\n\n"
        "Собрать заново: `python3 build_office.py`\n",
        encoding="utf-8",
    )
    for path in paths:
        print(path.name, path.stat().st_size)


if __name__ == "__main__":
    main()
