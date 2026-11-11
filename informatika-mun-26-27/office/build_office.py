#!/usr/bin/env python3
"""Генерация Word (.docx) и PowerPoint (.pptx) материалов."""
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
OUT.mkdir(parents=True, exist_ok=True)

ACCENT = RGBColor(0x0B, 0x5F, 0x4B)
INK = RGBColor(0x1C, 0x1A, 0x17)
MUTED = RGBColor(0x5C, 0x57, 0x4F)


def set_doc_defaults(doc: Document) -> None:
    style = doc.styles["Normal"]
    style.font.name = "Times New Roman"
    style.font.size = Pt(12)
    for section in doc.sections:
        section.top_margin = Cm(2)
        section.bottom_margin = Cm(2)
        section.left_margin = Cm(2.5)
        section.right_margin = Cm(2)


def add_title(doc: Document, text: str) -> None:
    p = doc.add_heading(text, level=0)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER


def add_h(doc: Document, text: str, level: int = 1) -> None:
    doc.add_heading(text, level=level)


def add_p(doc: Document, text: str, bold: bool = False) -> None:
    p = doc.add_paragraph()
    run = p.add_run(text)
    run.bold = bold
    run.font.name = "Times New Roman"
    run.font.size = Pt(12)


def add_bullets(doc: Document, items: list[str]) -> None:
    for item in items:
        p = doc.add_paragraph(item, style="List Bullet")
        for run in p.runs:
            run.font.name = "Times New Roman"
            run.font.size = Pt(12)


def add_table(doc: Document, headers: list[str], rows: list[list[str]]) -> None:
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    for i, h in enumerate(headers):
        table.rows[0].cells[i].text = h
    for r, row in enumerate(rows):
        for c, val in enumerate(row):
            table.rows[r + 1].cells[c].text = val
    doc.add_paragraph()


def build_zadaniya() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Муниципальный этап ВсОШ по информатике, 9–11 классы")
    add_p(doc, "Новый комплект заданий (уникальные аналоги прошлогодних задач с небольшим усложнением).")
    add_p(doc, "Каждая задача — 100 баллов. Ограничения: 1 с / 64 MB.", bold=True)

    add_h(doc, "№ 1. Кинотеатр — 100 баллов")
    add_p(
        doc,
        "В одном ряду кинотеатра находится n кресел. Для прохода зрителей и персонала "
        "нужно сделать один или несколько проходов между креслами.",
    )
    add_p(doc, "Ограничения рассадки:", bold=True)
    add_bullets(
        doc,
        [
            "у краёв ряда (у стен) может стоять не более 2 кресел подряд;",
            "между двумя соседними проходами — не более 5 кресел подряд;",
            "в ряду должен быть хотя бы один проход.",
        ],
    )
    add_p(doc, "Определите минимальное число проходов для ряда из n кресел.")
    add_p(doc, "Вход: одно натуральное число n (1 ≤ n ≤ 2·10⁹).")
    add_p(doc, "Выход: одно целое число — минимальное количество проходов.")
    add_p(doc, "Примеры: 4→1; 5→2; 10→3; 15→4.")

    add_h(doc, "№ 2. Чётный артикул — 100 баллов")
    add_p(
        doc,
        "Произведение цифр артикула должно равняться N. Нужно найти наименьший по величине "
        "чётный артикул из цифр {2,3,4,5,6,7,8,9}. Если невозможно — вывести -1.",
    )
    add_p(doc, "Вход: N (1 ≤ N ≤ 10¹⁸). Выход: минимальный чётный артикул или -1.")
    add_p(doc, "Примеры: 70→572; 16→28; 36→94; 101→-1; 7→-1; 8→8.")

    add_h(doc, "№ 3. Трубы — 100 баллов")
    add_p(
        doc,
        "Есть n труб. Чётную трубу можно многократно распиливать пополам. Нужно получить "
        "как можно больше труб одной длины; при ничьей выбрать наименьшую длину.",
    )
    add_p(doc, "Вход: n (1 ≤ n ≤ 10⁵); затем n чисел aᵢ в неубывающем порядке (1 ≤ aᵢ ≤ 10⁹).")
    add_p(doc, "Выход: два числа — количество и длина.")
    add_p(doc, "Примеры: 1 2 3 4 5 → 7 1;  3 6 12 → 7 3;  8 8 8 → 24 1;  7 → 1 7.")

    add_h(doc, "№ 4. Катеты на сетке — 100 баллов")
    add_p(
        doc,
        "Сетка n×m клеток. Хороший треугольник: равнобедренный прямоугольный; катет 1, 2 или 3; "
        "оба катета либо по осям, либо оба под 45°; вершины в узлах сетки. Посчитайте количество.",
    )
    add_p(doc, "Вход: n и m в отдельных строках (1 ≤ n, m ≤ 10⁸).")
    add_p(doc, "Выход: одно целое число.")
    add_p(doc, "Примеры: 1×1→4; 1×2→10; 2×2→28; 3×5→156; 5×5→312.")

    path = OUT / "Zadaniya_olimpiada.docx"
    doc.save(path)
    return path


def build_resheniya() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Разбор и эталонные идеи решений")

    add_h(doc, "№ 1. Кинотеатр")
    add_p(doc, "При p проходах максимум кресел: 4 + 5(p−1). Если n ≤ 4 → ответ 1, иначе 1 + ceil((n−4)/5).")
    add_p(doc, "Код:", bold=True)
    add_p(doc, "n = int(input())\nprint(1 if n <= 4 else 1 + (n - 4 + 5 - 1) // 5)")

    add_h(doc, "№ 2. Чётный артикул")
    add_bullets(
        doc,
        [
            "Разложить N на цифры 9..2.",
            "Если нельзя или нет чётной цифры — -1.",
            "Перебрать чётную цифру в конце, остальные по возрастанию; взять минимум.",
        ],
    )
    add_p(doc, "Пример: 70 → {2,5,7} → 572.")

    add_h(doc, "№ 3. Трубы")
    add_p(doc, "a = d·2^k (d нечётно) даёт 2^k труб длины d. Суммируем по d; max, при ничьей — меньшее d.")

    add_h(doc, "№ 4. Катеты на сетке")
    add_p(doc, "Для k ∈ {1,2,3}:")
    add_bullets(
        doc,
        [
            "оси: 4·max(0,n−k+1)·max(0,m−k+1);",
            "диагонали: 2·max(0,n−2k+1)·max(0,m−k+1) + 2·max(0,n−k+1)·max(0,m−2k+1).",
        ],
    )

    path = OUT / "Resheniya_olimpiada.docx"
    doc.save(path)
    return path


def build_programma() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Программа подготовки к олимпиаде — 12 часов")
    add_p(doc, "Для преподавателя. Формат: 6 блоков × 2 часа (2 дня по 6 ч или 3 дня по 4 ч).")

    add_h(doc, "Общее расписание")
    add_table(
        doc,
        ["Блок", "Часы", "Тема", "Практика", "Бонус"],
        [
            ["1", "0–2", "Формулы и моделирование", "praktika/01", "bonus/01"],
            ["2", "2–4", "Цифры и конструкция числа", "praktika/02", "bonus/02"],
            ["3", "4–6", "Массивы, частоты, распилы", "praktika/03", "bonus/03"],
            ["4", "6–8", "Комбинаторика на сетке", "praktika/04", "bonus/04"],
            ["5", "8–10", "Стратегия + микс", "praktika/05", "bonus/05"],
            ["6", "10–12", "Тренировочный тур", "комплект №1–4", "bonus/06 E*"],
        ],
    )

    blocks = [
        (
            "Блок 1. Формулы (2 ч)",
            [
                "0–15: вводная, ограничения → сложность",
                "15–55: вывод формулы «Кинотеатр», крайние случаи",
                "55–90: практика A–C",
                "90–120: бонус + разбор ошибок ceil/off-by-one",
            ],
        ),
        (
            "Блок 2. Цифры (2 ч)",
            [
                "Жадность 9→2; минимальное число",
                "Усложнение: чётный артикул",
                "Практика + бонус (длина ≤ K / палиндром)",
            ],
        ),
        (
            "Блок 3. Массивы (2 ч)",
            [
                "Нечётное ядро a=d·2^k",
                "Многократный vs одноразовый распил",
                "Практика на n=1e5",
            ],
        ),
        (
            "Блок 4. Сетка (2 ч)",
            [
                "Тип × ориентации × позиции",
                "Разбор «Катеты»",
                "Метод маленьких случаев для формулы",
            ],
        ),
        (
            "Блок 5. Стратегия (2 ч)",
            [
                "Порядок задач на туре",
                "Свои тесты: край, граница, −1, ничья",
                "Микс M1–M4",
            ],
        ),
        (
            "Блок 6. Тур (2 ч)",
            [
                "90 мин: 4 задачи комплекта + опционально E*",
                "30 мин: разбор и план домашней работы",
            ],
        ),
    ]
    for title, items in blocks:
        add_h(doc, title, level=2)
        add_bullets(doc, items)

    add_h(doc, "Как работать с быстрыми учениками")
    add_bullets(
        doc,
        [
            "Не ждать группу — сразу выдавать bonus/.",
            "На туре после 4 задач — E* или помощь одногруппникам вопросами (не кодом).",
            "Сверять ответы по файлу Otvety.docx / otvety-i-razbory.md.",
        ],
    )

    path = OUT / "Programma_12_chasov.docx"
    doc.save(path)
    return path


def build_shpargalka() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Шпаргалка ученику")
    add_p(doc, "Подготовка к олимпиаде по информатике (9–11 класс)")

    add_h(doc, "1. Формулы")
    add_bullets(
        doc,
        [
            "n до 2·10⁹ ⇒ только O(1) / O(log n)",
            "ceil(a/b) = (a+b−1)//b при a≥0",
            "Кинотеатр: n≤4 → 1, иначе 1+ceil((n−4)/5)",
        ],
    )

    add_h(doc, "2. Цифры")
    add_bullets(
        doc,
        [
            "Произведение = N: делить на 9..2; остаток ≠1 → −1",
            "Минимальное число — цифры по возрастанию",
            "Чётное: чётная цифра в конце, перебрать варианты",
            "70 → 257 (обычный), 572 (чётный)",
        ],
    )

    add_h(doc, "3. Распилы")
    add_bullets(
        doc,
        [
            "a = d·2^k → 2^k кусков длины d",
            "Сумма по ядрам; max; ничья → меньшее d",
        ],
    )

    add_h(doc, "4. Сетка")
    add_bullets(
        doc,
        [
            "Счёт = тип × ориентации × позиции",
            "Прямоугольники: n(n+1)/2 · m(m+1)/2",
            "Катеты 1..3: оси + диагонали 45°",
        ],
    )

    add_h(doc, "5. Тактика тура")
    add_bullets(
        doc,
        [
            "Сначала пробежать все условия",
            "Лёгкие — до конца, не застревать >40–50 мин",
            "Свои тесты: минимум, граница формулы, максимум, −1, ничья",
        ],
    )

    path = OUT / "Shpargalka_ucheniku.docx"
    doc.save(path)
    return path


def build_praktika() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Практика и бонусные задачи")
    add_p(doc, "Задачи для самостоятельной работы на интенсиве. Бонусы — для тех, кто решает быстрее.")

    add_h(doc, "Практика · Блок 1. Формулы")
    add_h(doc, "A. Автобус", 2)
    add_p(doc, "n сидений; края ≤3, между проходами ≤4; ≥1 проход. Мин. проходов. n≤2·10⁹.")
    add_p(doc, "Примеры: 3→1; 7→2; 8→2; 11→3.")
    add_h(doc, "B. Перерывы", 2)
    add_p(doc, "n минут, куски работы ≤k. Мин. перерывов (0, если n≤k).")
    add_p(doc, "Примеры: 5 5→0; 6 5→1; 20 5→3.")
    add_h(doc, "C. Вагоны", 2)
    add_p(doc, "n пассажиров, купе ≤4. Ответ ceil(n/4). Примеры: 1→1; 4→1; 5→2.")

    add_h(doc, "Практика · Блок 2. Цифры")
    add_bullets(
        doc,
        [
            "A. Минимальный номер с произведением цифр N (1→1; 70→257; 101→−1).",
            "B. Чётный артикул (70→572; 16→28; 7→−1).",
            "C. Сумма цифр минимального номера (70→14; 8→8; 101→−1).",
        ],
    )

    add_h(doc, "Практика · Блок 3. Массивы")
    add_bullets(
        doc,
        [
            "A. Трубы (многократный распил): 1 2 3 4 5 → 7 1.",
            "B. Частоты без распила; ничья → меньшее число.",
            "C. Один распил (сравнить с «Трубами»).",
        ],
    )

    add_h(doc, "Практика · Блок 4. Комбинаторика")
    add_bullets(
        doc,
        [
            "A. Катеты на сетке: 1×1→4; 2×2→28; 3×5→156.",
            "B. Прямоугольники: n(n+1)/2·m(m+1)/2 (1×1→1; 2×2→9).",
            "C. Только осевые катеты k=1..2: 1×1→4; 2×2→20.",
        ],
    )

    add_h(doc, "Практика · Блок 5. Микс")
    add_bullets(
        doc,
        [
            "M1. Серверная: края ≤3, середина ≤6 → max(1, ceil(n/6)).",
            "M2. Минимальное чётное с произведением 36 → 94.",
            "M3. Трубы для 10 12 7 8 → 8 1.",
            "M4. Катеты 4×4 → 176.",
        ],
    )

    add_h(doc, "Бонусы для быстрых")
    add_bullets(
        doc,
        [
            "Блок 1: параметры A,B,Pmin; учёт ширины корпуса W.",
            "Блок 2: длина ≤ K; минимальный палиндром.",
            "Блок 3: распил на 2 или 3; запросы частоты на отрезке.",
            "Блок 4: квадраты (оси + 45°); катеты 1..K.",
            "Блок 5–6: своя формула с 3 зонами; E* PIN с суммой цифр S.",
        ],
    )

    path = OUT / "Praktika_i_bonusy.docx"
    doc.save(path)
    return path


def build_otvety() -> Path:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "Ответы и краткие разборы")

    add_h(doc, "Практика 01")
    add_p(doc, "A: max(1, 1+(n−3)//4) → 1,2,2,3 для примеров.")
    add_p(doc, "B: 0 если n≤k, иначе ceil(n/k)−1 → 0,1,3.")
    add_p(doc, "C: (n+3)//4.")

    add_h(doc, "Практика 02")
    add_p(doc, "A: 1; 257; −1.  B: 572; 28; −1.  C: 14; 8; −1.")

    add_h(doc, "Практика 03")
    add_p(doc, "A: 7 1; 7 3.  B: для 1 2 2 3 3 → 2 2.  C: одноразовый распил ≠ многократному.")

    add_h(doc, "Практика 04")
    add_p(doc, "A: 4; 28; 156.  B: 1; 3; 9.  C: 4; 20.")

    add_h(doc, "Микс")
    add_p(doc, "M1: max(1,(n+5)//6).  M2: 94.  M3: 8 1.  M4: 176.")

    path = OUT / "Otvety.docx"
    doc.save(path)
    return path


def _blank_slide(prs: Presentation):
    return prs.slides.add_slide(prs.slide_layouts[6])  # blank


def _rect(slide, left, top, width, height, fill_rgb: RGBColor | None = None):
    shape = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, left, top, width, height)
    shape.line.fill.background()
    if fill_rgb is not None:
        shape.fill.solid()
        shape.fill.fore_color.rgb = fill_rgb
    else:
        shape.fill.background()
    return shape


def _textbox(slide, left, top, width, height, text: str, size: int = 28, bold: bool = False, color: RGBColor = INK, align=PP_ALIGN.LEFT):
    box = slide.shapes.add_textbox(left, top, width, height)
    tf = box.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    run.font.size = PptPt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    run.font.name = "Calibri"
    return box


def _bullets(slide, left, top, width, height, lines: list[str], size: int = 22):
    box = slide.shapes.add_textbox(left, top, width, height)
    tf = box.text_frame
    tf.word_wrap = True
    for i, line in enumerate(lines):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.text = "•  " + line
        p.level = 0
        p.font.size = PptPt(size)
        p.font.color.rgb = INK
        p.font.name = "Calibri"
        p.space_after = PptPt(10)
    return box


def add_title_slide(prs, title: str, subtitle: str) -> None:
    slide = _blank_slide(prs)
    _rect(slide, Inches(0), Inches(0), Inches(13.333), Inches(0.35), ACCENT)
    _textbox(slide, Inches(0.7), Inches(2.2), Inches(12), Inches(1.5), title, size=40, bold=True, color=ACCENT)
    _textbox(slide, Inches(0.7), Inches(3.8), Inches(12), Inches(1.2), subtitle, size=22, color=MUTED)


def add_content_slide(prs, title: str, bullets: list[str], footnote: str | None = None) -> None:
    slide = _blank_slide(prs)
    _rect(slide, Inches(0), Inches(0), Inches(13.333), Inches(0.25), ACCENT)
    _textbox(slide, Inches(0.6), Inches(0.45), Inches(12), Inches(0.8), title, size=32, bold=True, color=ACCENT)
    _bullets(slide, Inches(0.8), Inches(1.4), Inches(11.5), Inches(5.2), bullets, size=22)
    if footnote:
        _textbox(slide, Inches(0.8), Inches(6.8), Inches(11.5), Inches(0.5), footnote, size=16, color=MUTED)


def build_pptx() -> Path:
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)

    add_title_slide(
        prs,
        "Подготовка к олимпиаде по информатике",
        "Интенсив 12 часов · 9–11 классы · муниципальный уровень",
    )

    add_content_slide(
        prs,
        "Карта интенсива",
        [
            "0–2 ч — Формулы и моделирование",
            "2–4 ч — Цифры и конструкция числа",
            "4–6 ч — Массивы, частоты, распилы",
            "6–8 ч — Комбинаторика на сетке",
            "8–10 ч — Стратегия тура + микс",
            "10–12 ч — Тренировочный тур и разбор",
        ],
        "В каждом блоке: теория → разбор → практика → бонус для быстрых",
    )

    add_content_slide(
        prs,
        "Блок 1 · Формулы",
        [
            "Ограничение n ≤ 2·10⁹ ⇒ цикл по n невозможен",
            "Схема: при ответе p оценить максимум объектов → найти мин. p",
            "Кинотеатр: края ≤2, середина ≤5 → max = 4 + 5(p−1)",
            "Если n ≤ 4 → 1, иначе 1 + ceil((n−4)/5)",
            "Ловушки: ceil vs floor, край n=1, off-by-one",
        ],
        "Практика: Автобус / Перерывы / Вагоны",
    )

    add_content_slide(
        prs,
        "Блок 1 · Пример «Кинотеатр»",
        [
            "n=4 → 1 проход (две группы по ≤2)",
            "n=5 → 2 прохода",
            "n=10 → 3; n=15 → 4",
            "Проверяйте границу перехода 4 ↔ 5",
            "Формула обязана работать и на 2·10⁹",
        ],
    )

    add_content_slide(
        prs,
        "Блок 2 · Цифры",
        [
            "Жадность: делим N на цифры от 9 к 2",
            "Остаток ≠ 1 → невозможно (−1)",
            "Минимальное число — цифры по возрастанию",
            "Чётный артикул: чётная цифра в конце",
            "70 → обычный 257; чётный 572",
        ],
        "Практика: минимальный номер / чётный / сумма цифр",
    )

    add_content_slide(
        prs,
        "Блок 2 · Алгоритм чётного артикула",
        [
            "1) Набрать мультимножество цифр жадно",
            "2) Если нет чётной цифры — −1",
            "3) Перебрать, какую чётную поставить в конец",
            "4) Остальные отсортировать по возрастанию",
            "5) Выбрать минимальное получившееся число",
        ],
        "Пример: 16 → {8,2} → кандидаты 28 и 82 → 28",
    )

    add_content_slide(
        prs,
        "Блок 3 · Массивы и распилы",
        [
            "n ≤ 10⁵ ⇒ O(n) / O(n log n)",
            "Нечётное ядро: a = d · 2^k",
            "Многократный распил → 2^k труб длины d",
            "Суммируем вклады; max; ничья → меньшее d",
            "1 2 3 4 5 → 7 1",
        ],
        "Важно отличать от «одного распила» (городки)",
    )

    add_content_slide(
        prs,
        "Блок 4 · Комбинаторика на сетке",
        [
            "n, m до 1e8 ⇒ только формула",
            "Шаблон: тип фигуры × ориентации × позиции",
            "Катеты k=1..3: оси + диагонали 45°",
            "Метод: таблица маленьких n,m → угадать формулу",
            "1×1→4; 2×2→28; 3×5→156",
        ],
        "Практика: прямоугольники; только осевые катеты",
    )

    add_content_slide(
        prs,
        "Блок 5 · Стратегия тура",
        [
            "Пробежать все условия за 15–20 минут",
            "Пометить легко / средне / трудно",
            "Сначала закрыть все лёгкие",
            "Не сидеть >40–50 мин без прогресса",
            "Свои тесты: край, граница формулы, −1, ничья",
        ],
    )

    add_content_slide(
        prs,
        "Блок 5 · Микс (самостоятельно)",
        [
            "M1. Серверная: края ≤3, середина ≤6",
            "M2. Чётное число с произведением 36",
            "M3. Трубы для массива 10 12 7 8",
            "M4. Катеты на сетке 4×4",
            "Кто закончил — бонусные H-задачи",
        ],
        "Ответы: M2=94; M3=8 1; M4=176",
    )

    add_content_slide(
        prs,
        "Блок 6 · Тренировочный тур (90 мин)",
        [
            "A — Кинотеатр",
            "B — Чётный артикул",
            "C — Трубы",
            "D — Катеты на сетке",
            "E* — бонус PIN (произведение N и сумма S)",
        ],
        "После тура — 30 минут общего разбора",
    )

    add_content_slide(
        prs,
        "Чек-лист перед сдачей",
        [
            "Примеры из условия проходят",
            "Проверен край n=1 / пустые случаи",
            "Целочисленное деление верное (// и ceil)",
            "Формат вывода точный",
            "Ничья разобрана по условию",
        ],
    )

    add_title_slide(
        prs,
        "Удачи на олимпиаде!",
        "Материалы: папка office/ · Word-условия · эта шпаргалка-презентация",
    )

    path = OUT / "Prezentaciya_podgotovka_12h.pptx"
    prs.save(path)
    return path


def build_pptx_zadaniya() -> Path:
    """Короткая презентация по 4 задачам комплекта."""
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)

    add_title_slide(
        prs,
        "Олимпиадный комплект 9–11",
        "4 задачи × 100 баллов · новые аналоги прошлогодних",
    )
    add_content_slide(
        prs,
        "№1. Кинотеатр",
        [
            "n кресел, минимум проходов",
            "Края ≤ 2, между проходами ≤ 5, ≥ 1 проход",
            "n до 2·10⁹ — нужна формула",
            "Примеры: 4→1; 5→2; 10→3; 15→4",
        ],
    )
    add_content_slide(
        prs,
        "№2. Чётный артикул",
        [
            "Произведение цифр = N",
            "Минимальное чётное число из цифр 2–9",
            "Если нельзя — −1",
            "Примеры: 70→572; 16→28; 101→−1",
        ],
    )
    add_content_slide(
        prs,
        "№3. Трубы",
        [
            "Многократный распил чётных длин пополам",
            "Максимум труб одной длины",
            "При ничьей — меньшая длина",
            "Пример: 1 2 3 4 5 → 7 1",
        ],
    )
    add_content_slide(
        prs,
        "№4. Катеты на сетке",
        [
            "Равнобедренные прямоугольные треугольники",
            "Катет 1, 2 или 3; оси или 45°",
            "n, m до 1e8 — формула O(1)",
            "Примеры: 1×1→4; 2×2→28; 3×5→156",
        ],
    )

    path = OUT / "Prezentaciya_zadaniya_komplekt.pptx"
    prs.save(path)
    return path


def main() -> None:
    paths = [
        build_zadaniya(),
        build_resheniya(),
        build_programma(),
        build_shpargalka(),
        build_praktika(),
        build_otvety(),
        build_pptx(),
        build_pptx_zadaniya(),
    ]
    readme = OUT / "README.md"
    readme.write_text(
        "# Office-материалы (Word и PowerPoint)\n\n"
        "| Файл | Формат | Назначение |\n"
        "|------|--------|------------|\n"
        "| `Zadaniya_olimpiada.docx` | Word | Условия 4 задач комплекта |\n"
        "| `Resheniya_olimpiada.docx` | Word | Разбор идей решений |\n"
        "| `Programma_12_chasov.docx` | Word | Программа интенсива для преподавателя |\n"
        "| `Shpargalka_ucheniku.docx` | Word | Шпаргалка ученику |\n"
        "| `Praktika_i_bonusy.docx` | Word | Практика + бонусы для быстрых |\n"
        "| `Otvety.docx` | Word | Ответы к практике и миксу |\n"
        "| `Prezentaciya_podgotovka_12h.pptx` | PowerPoint | Презентация интенсива (12 ч) |\n"
        "| `Prezentaciya_zadaniya_komplekt.pptx` | PowerPoint | Презентация по 4 задачам комплекта |\n\n"
        "Пересобрать: `python3 build_office.py`\n",
        encoding="utf-8",
    )
    for p in paths:
        print(p.name, p.stat().st_size)


if __name__ == "__main__":
    main()
