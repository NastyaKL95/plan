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
    title(doc, "Программа курса 12 часов")
    p(doc, "Для преподавателя. Курс независим от муниципального комплекта.")
    h(doc, "Структура каждого блока (2 часа)")
    bullets(doc, [
        "0–20 мин — тема простыми словами",
        "20–50 мин — разбор учебных задач",
        "50–95 мин — практика группы",
        "95–115 мин — дополнительные задачи для быстрых",
        "115–120 мин — ответы и ошибки",
    ])
    h(doc, "Расписание")
    table(
        doc,
        ["Блок", "Тема", "Практика", "Дополнительно"],
        [
            ["1", "Формулы", "praktika/01", "dopolnitelno/01"],
            ["2", "Цифры", "praktika/02", "dopolnitelno/02"],
            ["3", "Массивы", "praktika/03", "dopolnitelno/03"],
            ["4", "Сетка", "praktika/04", "dopolnitelno/04"],
            ["5", "Префиксы / 2 указателя", "praktika/05", "dopolnitelno/05"],
            ["6", "Мини-тур T1–T4", "temy/06", "dopolnitelno/06"],
        ],
    )
    path = OUT / "Programma.docx"
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
    p(doc, "Практика 5: pref[r]−pref[l−1]; длина 3 на примере; 4 подотрезка.")
    p(doc, "Тур: T1 формула; T2 жадность 9..2; T3 Counter; T4 сумма по s.")
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

    title_slide(prs, "Олимпиада по информатике · курс 12 часов", "9–11 класс · самостоятельный курс · темы, разборы, практика, доп. задачи")
    content(prs, "Что это за курс", [
        "Не привязан к задачам муниципального комплекта",
        "6 блоков по 2 часа: от формул до мини-тура",
        "В каждом блоке: объяснение → разбор → практика → доп. задачи",
        "Есть Word-материалы и эта презентация",
    ])
    content(prs, "Карта 12 часов", [
        "1. Формулы и упаковка",
        "2. Цифры числа",
        "3. Массивы и частоты",
        "4. Подсчёт на сетке",
        "5. Префикс-суммы и два указателя",
        "6. Мини-тур из 4 задач курса",
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
        "Практика: P1A–P1C · Доп.: две вместимости, растущая норма",
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
        "Практика: P2A–P2C · Доп.: перестановка цифр, длина L и сумма S",
    ])

    section(prs, "3", "Массивы и частоты", "Один проход вместо двойного цикла")
    content(prs, "Тема 3 · Разборы", [
        "Мода: Counter, при ничьей — меньшее значение",
        "Серия подряд равных — текущий счётчик длины",
        "Число различных — set / словарь",
        "Практика: P3A–P3C · Доп.: вторая частота, сжатие серий",
    ])

    section(prs, "4", "Подсчёт на сетке", "Тип фигуры × размер × позиции")
    content(prs, "Тема 4 · Разборы", [
        "Прямоугольники: n(n+1)/2 · m(m+1)/2",
        "Квадраты: сумма (n−s+1)(m−s+1)",
        "Диагональ клеток: n+m−gcd(n,m)",
        "Всегда проверяйте 1×1 и 2×2",
        "Практика: P4A–P4C · Доп.: 45°, площадь P",
    ])

    section(prs, "5", "Префиксы и два указателя", "Суммы отрезков и окна за линейное время")
    content(prs, "Тема 5 · Разборы", [
        "Префиксы: сумма [l..r] = pref[r]−pref[l−1]",
        "Два указателя на неотрицательном массиве",
        "Задача: максимальная длина с суммой ≤ S",
        "Практика: P5A–P5C · Доп.: сумма ≥X, окно длины K",
    ])

    section(prs, "6", "Мини-тур", "90 минут · 4 задачи курса · затем разбор")
    content(prs, "Задачи тура", [
        "T1 Рейсы — формулы ⌈n/k⌉",
        "T2 Код замка — минимальное число с произведением цифр N",
        "T3 Мода массива — частоты и ничья",
        "T4 Площадки — число квадратов на n×m",
        "Кто сдал всё — дополнительные E1–E3",
    ])
    content(prs, "Итог курса", [
        "Формула вместо медленного цикла",
        "Жадная сборка числа из цифр",
        "Частоты и серии на массивах",
        "Комбинаторика сетки",
        "Префиксы и два указателя",
    ], "Материалы: kurs-olimpiada-12h/ и office/*.docx")

    title_slide(prs, "Удачи на олимпиаде!", "Temy_i_razbory.docx · Praktika_i_dopolnitelno.docx · эта презентация")

    path = OUT / "Prezentaciya_kurs_12h.pptx"
    prs.save(path)
    return path


def build_pptx_tur() -> Path:
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    title_slide(prs, "Мини-тур курса", "4 задачи · 90 минут · свои условия курса")
    content(prs, "T1. Рейсы", ["Перевезти n учеников по k в рейсе", "Ответ ⌈n/k⌉", "n,k до 1e18 — только формула"])
    content(prs, "T2. Код замка", ["Минимальное число с произведением цифр N", "Жадность 9..2", "Если нельзя — −1"])
    content(prs, "T3. Мода массива", ["Максимальная частота и значение", "Ничья → меньшее значение", "n ≤ 1e5"])
    content(prs, "T4. Площадки", ["Число квадратов на поле n×m", "Сумма по стороне s", "Проверка: 2×2 → 5"])
    path = OUT / "Prezentaciya_mini_tur.pptx"
    prs.save(path)
    return path


def main():
    paths = [
        build_temy(),
        build_programma(),
        build_praktika(),
        build_otvety(),
        build_shpargalka(),
        build_pptx(),
        build_pptx_tur(),
    ]
    (OUT / "README.md").write_text(
        "# Office-материалы самостоятельного курса 12 часов\n\n"
        "| Файл | Назначение |\n|------|------------|\n"
        "| `Temy_i_razbory.docx` | Подробные темы и разборы |\n"
        "| `Programma.docx` | Программа для преподавателя |\n"
        "| `Praktika_i_dopolnitelno.docx` | Практика + доп. задачи |\n"
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
