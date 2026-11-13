#!/usr/bin/env python3
"""Word + PowerPoint для курса 2 (12 часов) — собираются из content.py и solutions/."""
from __future__ import annotations

import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent
ROOT = OUT.parent
sys.path.insert(0, str(ROOT))
sys.path.insert(0, str(ROOT.parent / "tools"))

from content import BLOCKS, COURSE_SUB, COURSE_TITLE, MINI_TOUR, TIMING  # noqa: E402
from office_utils import (ACCENT2, d_bullets, d_code, d_h, d_p, d_table, d_tests, d_title,  # noqa: E402
                          new_doc, new_prs, plain, s_code, s_content, s_section, s_title)


def code_of(name):
    return (ROOT / "solutions" / name).read_text(encoding="utf-8")


def src(r):
    name, url = r["source"]
    return f"{name}{', ' + r['letter'] if r['letter'] else ''}. {url}"


def theory_docx(doc, lines):
    for line in lines:
        if line.startswith("- "):
            d_bullets(doc, [line[2:]])
        elif line[:2] in ("1.", "2.", "3.", "4."):
            d_bullets(doc, [line[3:]], style="List Number")
        else:
            d_p(doc, line)


# ------------------------------------------------------------------ Word

def build_programma():
    doc = new_doc()
    d_title(doc, COURSE_TITLE, COURSE_SUB)
    d_h(doc, "Расписание")
    d_table(doc, ["Блок", "Часы", "Тема", "Реальные задачи"],
            [[str(b["num"]), b["hours"], b["title"], ", ".join(r["name"] for r in b["real"])] for b in BLOCKS])
    d_h(doc, "Структура 2-часового блока")
    d_table(doc, ["Время", "Что делаем"], [list(t) for t in TIMING])
    d_p(doc, "В блоке 6 последний час — мини-тур из 4 задач.")
    d_h(doc, "Цели блоков")
    for b in BLOCKS:
        d_p(doc, f"**Блок {b['num']}. {b['title']}.** {b['goal']}")
    d_h(doc, "Как работать с быстрой группой")
    d_bullets(doc, [
        "Учебные задачи практики — обязательный минимум; кто решил их, сразу переходит к реальным задачам блока.",
        "Задачи «Для тех, кто решает быстрее» — уровень выше среднего муниципального этапа.",
        "Задачи Codeforces можно сдавать на сайте с автоматической проверкой.",
        "Задачи ВсОШ проверяются на примерах из PDF и своих тестах; эталонные решения — в папке solutions/."])
    path = OUT / "Programma_kurs2.docx"
    doc.save(path)
    return path


def build_lekcii():
    doc = new_doc()
    d_title(doc, "Курс 2: лекции и разборы", COURSE_SUB)
    for b in BLOCKS:
        doc.add_page_break()
        d_h(doc, f"Блок {b['num']}. {b['title']} (часы {b['hours']})", 1)
        d_p(doc, f"**Цель:** {b['goal']}")
        d_h(doc, "Лекция", 2)
        for head, lines in b["theory"]:
            d_h(doc, head, 3)
            theory_docx(doc, lines)
        ex = b["example"]
        d_h(doc, f"Разобранный пример: {ex['name']}", 2)
        d_p(doc, ex["text"])
        d_tests(doc, ex["tests"])
        d_p(doc, "Разбор по шагам:", bold=True)
        d_bullets(doc, ex["steps"], style="List Number")
        d_code(doc, code_of(ex["code"]))
        d_h(doc, "Практика", 2)
        for t in b["practice"]:
            d_p(doc, f"**{t['name']}.** {t['text']}")
            d_tests(doc, t["tests"])
        d_h(doc, "Реальные олимпиадные задачи", 2)
        for r in b["real"]:
            d_h(doc, r["name"], 3)
            d_p(doc, f"Источник: {src(r)}")
            d_p(doc, r["text"])
            d_p(doc, f"**Идея решения.** {r['idea']}")
        d_h(doc, "Для тех, кто решает быстрее", 2)
        for t in b["bonus"]:
            d_p(doc, f"**{t['name']}.** {t['text']}")
            d_tests(doc, t["tests"])
    path = OUT / "Lekcii_i_razbory_kurs2.docx"
    doc.save(path)
    return path


def build_zadachi():
    doc = new_doc()
    d_title(doc, "Курс 2: задачи для учеников", "Практика, реальные олимпиадные задачи, задачи для быстрых и мини-тур (без ответов)")
    for b in BLOCKS:
        d_h(doc, f"Блок {b['num']}. {b['title']}", 1)
        d_h(doc, "Практика", 2)
        for t in b["practice"]:
            d_p(doc, f"**{t['name']}.** {t['text']}")
            d_tests(doc, t["tests"])
        d_h(doc, "Реальные задачи", 2)
        for r in b["real"]:
            d_p(doc, f"**{r['name']}** ({src(r)})")
            d_p(doc, r["text"])
        d_h(doc, "Для тех, кто решает быстрее", 2)
        for t in b["bonus"]:
            d_p(doc, f"**{t['name']}.** {t['text']}")
            d_tests(doc, t["tests"])
    doc.add_page_break()
    d_h(doc, MINI_TOUR["title"], 1)
    d_bullets(doc, MINI_TOUR["rules"])
    for t in MINI_TOUR["tasks"]:
        d_p(doc, f"**{t['name']}.** {t['text']}")
        d_tests(doc, t["tests"])
    path = OUT / "Zadachi_dlya_uchenikov_kurs2.docx"
    doc.save(path)
    return path


def build_realnye():
    doc = new_doc()
    d_title(doc, "Курс 2: реальные олимпиадные задачи", "Муниципальные этапы ВсОШ (Казань, Москва, Владивосток) и Codeforces")
    rows = [[str(b["num"]), r["name"], src(r)] for b in BLOCKS for r in b["real"]]
    d_table(doc, ["Блок", "Задача", "Источник"], rows)
    for b in BLOCKS:
        d_h(doc, f"Блок {b['num']}. {b['title']}", 1)
        for r in b["real"]:
            d_h(doc, r["name"], 2)
            d_p(doc, f"Источник: {src(r)}")
            d_p(doc, r["text"])
            d_p(doc, f"**Идея решения.** {r['idea']}")
            if r["code"]:
                d_p(doc, "Эталонное решение (Python):", bold=True)
                d_code(doc, code_of(r["code"]))
    path = OUT / "Realnye_zadachi_kurs2.docx"
    doc.save(path)
    return path


def build_otvety():
    doc = new_doc()
    d_title(doc, "Курс 2: ответы, подсказки и решения", "Для учителя")
    for b in BLOCKS:
        d_h(doc, f"Блок {b['num']}. {b['title']}", 1)
        for kind, items in (("Практика", b["practice"]), ("Для быстрых", b["bonus"])):
            d_h(doc, kind, 2)
            for t in items:
                d_p(doc, f"**{t['name']}** — {t['hint']}")
                d_code(doc, code_of(t["code"]))
    d_h(doc, "Мини-тур", 1)
    for t in MINI_TOUR["tasks"]:
        d_p(doc, f"**{t['name']}** — {t['hint']}")
        d_code(doc, code_of(t["code"]))
    path = OUT / "Otvety_i_resheniya_kurs2.docx"
    doc.save(path)
    return path


# ------------------------------------------------------------------ PowerPoint

def task_lines(t):
    lines = [t["text"], ""]
    for i, o in t["tests"]:
        lines.append(f"Вход: {i.replace(chr(10), ' / ')}   →   Выход: {o.replace(chr(10), ' / ')}")
    return lines


def build_prezentaciya():
    prs = new_prs()
    s_title(prs, COURSE_TITLE, COURSE_SUB)
    s_content(prs, "План курса", [f"- Блок {b['num']} (ч. {b['hours']}): {b['title']}" for b in BLOCKS]
              + ["", "Каждый блок: лекция → разобранный пример → практика → реальные задачи → задачи для быстрых"])
    s_content(prs, "Как устроен каждый блок (2 часа)", [f"- {t}: {w}" for t, w in TIMING])
    for b in BLOCKS:
        s_section(prs, f"Блок {b['num']}", b["title"], b["goal"])
        for head, lines in b["theory"]:
            s_content(prs, head, [x for x in lines], size=20 if sum(len(x) for x in lines) < 600 else 17)
        ex = b["example"]
        s_content(prs, f"Разбор: {ex['name']}", task_lines(ex), foot="Сначала обсуждаем идею с классом, потом пишем код")
        s_content(prs, f"Разбор: {ex['name']} — по шагам", [f"{i}. {s}" for i, s in enumerate(ex["steps"], 1)], size=19)
        s_code(prs, f"Код: {ex['name']}", code_of(ex["code"]))
        lines = []
        for t in b["practice"]:
            lines += [f"- {t['name']}: {t['text']}"]
        s_content(prs, "Практика (самостоятельно, 35 минут)", lines, size=18)
        for r in b["real"]:
            s_content(prs, f"Реальная задача: {r['name']}",
                      [r["text"], "", "Идея: " + r["idea"]],
                      foot="Источник: " + src(r), size=17 if len(r["idea"]) > 350 else 19, color=ACCENT2)
        lines = [f"- {t['name']}: {t['text']}" for t in b["bonus"]]
        s_content(prs, "Для тех, кто решает быстрее", lines, size=18, color=ACCENT2)
    s_content(prs, "Итоги курса: что нужно уметь", [
        "- Оценивать сложность по ограничениям: n ≤ 20 — маски, n ≤ 5000 — O(n²), n ≤ 2·10⁵ — O(n log n)",
        "- Обрабатывать строки посимвольно и считать столбиком",
        "- Сортировать и применять жадность с доказательством обменом; два указателя",
        "- Бинарный поиск в массиве и по ответу; НОД и алгоритм Евклида",
        "- BFS: граф, поле, несколько источников, 0-1 BFS",
        "- ДП: состояние, переход, база, порядок"])
    path = OUT / "Prezentaciya_kurs2_12h.pptx"
    prs.save(path)
    return path


def build_mini_tur():
    prs = new_prs()
    s_title(prs, "Мини-тур курса 2", "4 задачи · 60 минут · все темы курса")
    s_content(prs, "Правила", [f"- {r}" for r in MINI_TOUR["rules"]])
    for t in MINI_TOUR["tasks"]:
        s_content(prs, t["name"], task_lines(t))
    s_section(prs, "Разбор", "Разбор мини-тура", "Идеи и код решений")
    for t in MINI_TOUR["tasks"]:
        s_code(prs, f"Разбор: {t['name']}", code_of(t["code"]), note=[t["hint"]])
    path = OUT / "Prezentaciya_mini_tur_kurs2.pptx"
    prs.save(path)
    return path


def main():
    for fn in (build_programma, build_lekcii, build_zadachi, build_realnye, build_otvety,
               build_prezentaciya, build_mini_tur):
        print("OK", fn().name)


if __name__ == "__main__":
    main()
