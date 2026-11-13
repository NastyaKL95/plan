#!/usr/bin/env python3
"""Word, PowerPoint и PDF для варианта 2 муниципального этапа."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUT = ROOT / "office"
sys.path.insert(0, str(ROOT.parent / "tools"))

from office_utils import (ACCENT2, md_to_docx, new_doc, new_prs, plain, s_code, s_content,  # noqa: E402
                          s_section, s_title)

SOL = ROOT / "solutions"

TASKS = [
    dict(num=1, name="Зал с проходами", code="01_ryady.py",
         cond=["n кресел в ряду; у стены — не больше a кресел подряд, между проходами — не больше b.",
               "Нужен хотя бы один проход. Минимальное число проходов?",
               "n, a, b ≤ 10¹⁸.", "", "Пример: 10 3 4 → 2;  11 2 5 → 3;  4 2 5 → 1"],
         idea=["При p проходах помещается максимум 2a + (p − 1)·b кресел.",
               "n ≤ 2a → 1 проход.",
               "Иначе p = 1 + ⌈(n − 2a) / b⌉.",
               "⌈x / y⌉ = (x + y − 1) // y — без вещественных чисел.",
               "Ловушка: 10¹⁸ → long long; цикл по p не пройдёт."]),
    dict(num=2, name="Самый большой код", code="02_maks_nomer.py",
         cond=["Код — не больше K цифр (без нулей), произведение цифр равно N.",
               "Найти наибольший такой код или −1.", "N ≤ 10¹⁸, K ≤ 1000.", "",
               "Пример: 70 3 → 752;  70 5 → 75211;  100 2 → −1;  1 3 → 111"],
         idea=["c(N) — минимум цифр: жадно делим на 9, 8, …, 2. Остаток ≠ 1 или c(N) > K → −1.",
               "Длиннее = больше: берём ровно K цифр (лишние — единицы).",
               "Слева направо ставим наибольшую d (9…1), такую что d | N и c(N/d) ≤ оставшихся позиций.",
               "«Жадность + проверка, что остаток можно достроить» — универсальный приём."]),
    dict(num=3, name="Счастливые отрезки", code="03_otrezki.py",
         cond=["Массив из n чисел (бывают отрицательные), число K.",
               "Сколько отрезков с суммой, делящейся на K?", "n ≤ 2·10⁵, K ≤ 10⁹.", "",
               "Пример: 5 3 / 1 2 3 4 5 → 7"],
         idea=["Префиксные суммы: сумма [l, r] = S_r − S_(l−1).",
               "Делится на K ⇔ S_r mod K = S_(l−1) mod K.",
               "Считаем пары равных остатков словарём, S₀ = 0 учитываем сразу.",
               "Ловушки: отрицательный остаток в C++ — ((s % K) + K) % K; ответ до 2·10¹⁰ — 64 бита."]),
    dict(num=4, name="Квадраты на клетчатом поле", code="04_kvadraty.py",
         cond=["Поле n × m клеток. Сколько квадратов с вершинами в узлах сетки (в том числе повёрнутых)?",
               "Ответ по модулю 10⁹ + 7.", "n, m ≤ 10⁹.", "", "Пример: 1 1 → 1;  2 2 → 6;  3 3 → 20;  2 5 → 18"],
         idea=["В «ровный» квадрат со стороной s вписано ровно s квадратов с вершинами в узлах (сдвиг t = 0…s−1).",
               "Ровных квадратов со стороной s: (n − s + 1)(m − s + 1).",
               "Ответ = Σ (n − s + 1)(m − s + 1)·s, s = 1…min(n, m).",
               "Для 10⁹: раскрыть скобки и применить формулы Σs, Σs², Σs³.",
               "Проверка 2 × 2: 4·1 + 1·2 = 6."]),
]


def build_docx():
    paths = []
    for md, name, title in (("zadaniya.md", "Zadaniya_variant2.docx", None),
                            ("resheniya.md", "Razbor_variant2.docx", None)):
        doc = new_doc()
        md_to_docx(ROOT / md, doc)
        path = OUT / name
        doc.save(path)
        paths.append(path)
    return paths


def build_pptx():
    prs = new_prs()
    s_title(prs, "Муниципальный этап, 9–11 классы — Вариант 2",
            "Тренировочный комплект: 4 задачи по 100 баллов, подзадачи, разбор решений")
    s_content(prs, "Что изменилось по сравнению с прошлым годом", [
        "- Параметры в условии вместо констант (Зал с проходами: a и b вместо 2 и 5)",
        "- Ограничения до 10¹⁸ — только формула или O(log n)",
        "- Отрицательные числа и остатки (Счастливые отрезки)",
        "- Ответ по модулю 10⁹ + 7 и наклонные квадраты (геометрия + комбинаторика)",
        "- Подзадачи: даже неполное решение приносит баллы"])
    for t in TASKS:
        s_section(prs, f"№ {t['num']}", t["name"], "100 баллов")
        s_content(prs, f"№ {t['num']}. {t['name']} — условие", t["cond"])
        s_content(prs, f"№ {t['num']}. {t['name']} — идея", [f"- {x}" for x in t["idea"]], color=ACCENT2)
        s_code(prs, f"№ {t['num']}. Код (Python)", (SOL / t["code"]).read_text(encoding="utf-8"))
    s_content(prs, "Стратегия на туре", [
        "- Прочитать все 4 задачи, оценить ограничения каждой подзадачи",
        "- Сначала сдать простые подзадачи перебором — это гарантированные баллы",
        "- Перебор оставить: им удобно проверять быстрое решение на случайных тестах",
        "- Типы данных: 10¹⁸ и суммы — 64 бита, остатки от отрицательных — аккуратно"])
    path = OUT / "Prezentaciya_razbor_variant2.pptx"
    prs.save(path)
    return path


def build_pdf():
    from fpdf import FPDF

    pdf = FPDF()
    pdf.set_auto_page_break(True, 15)
    pdf.add_font("DejaVu", "", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
    pdf.add_font("DejaVu", "B", "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")
    pdf.add_font("Mono", "", "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf")
    pdf.add_font("Mono", "B", "/usr/share/fonts/truetype/dejavu/DejaVuSansMono-Bold.ttf")
    pdf.add_page()
    width = pdf.w - pdf.l_margin - pdf.r_margin
    lines = (ROOT / "zadaniya.md").read_text(encoding="utf-8").split("\n")
    i = 0
    while i < len(lines):
        line = lines[i]
        if line.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                if not re.match(r"^\|[\s\-|:]+\|$", lines[i]):
                    rows.append([plain(c.strip().replace("`", "")) for c in lines[i].strip("|").split("|")])
                i += 1
            pdf.set_font("Mono", size=10)
            with pdf.table(col_widths=tuple([1] * len(rows[0])), text_align="LEFT", line_height=6) as table:
                for r in rows:
                    row = table.row()
                    for c in r:
                        row.cell(c.replace("<br>", "\n"))
            pdf.ln(3)
            continue
        text = plain(line).replace("**", "").replace("`", "")
        if line.startswith("# "):
            pdf.set_font("DejaVu", "B", 15)
            pdf.multi_cell(width, 8, text[2:], align="C")
            pdf.ln(2)
        elif line.startswith("## "):
            if not line.startswith("## № 1"):
                pdf.add_page()
            pdf.set_font("DejaVu", "B", 13)
            pdf.multi_cell(width, 8, text[3:])
        elif line.startswith("### "):
            pdf.set_font("DejaVu", "B", 11)
            pdf.ln(1)
            pdf.multi_cell(width, 7, text[4:])
        elif line.strip() in ("", "---"):
            pdf.ln(2)
        else:
            pdf.set_font("DejaVu", "", 11)
            pdf.multi_cell(width, 6, ("•  " + text[2:]) if line.startswith("- ") else text)
        i += 1
    path = ROOT / "zadaniya.pdf"
    pdf.output(str(path))
    return path


def main():
    OUT.mkdir(exist_ok=True)
    for p in build_docx() + [build_pptx(), build_pdf()]:
        print("OK", p.relative_to(ROOT))


if __name__ == "__main__":
    main()
