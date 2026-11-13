#!/usr/bin/env python3
"""Собирает Markdown-материалы курса 2 из content.py и проверяет примеры эталонными решениями."""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

from content import BLOCKS, COURSE_SUB, COURSE_TITLE, MINI_TOUR, TIMING

ROOT = Path(__file__).resolve().parent
SOL = ROOT / "solutions"


def code_of(name):
    return (SOL / name).read_text(encoding="utf-8")


def check_examples():
    count = 0
    items = [MINI_TOUR["tasks"]] + [[b["example"]] + b["practice"] + b["bonus"] for b in BLOCKS]
    for group in items:
        for t in group:
            for inp, out in t["tests"]:
                got = subprocess.run([sys.executable, str(SOL / t["code"])], input=inp + "\n",
                                     text=True, capture_output=True, check=True).stdout.strip()
                assert got == out, (t["name"], inp, got, out)
                count += 1
    return count


def tests_md(tests):
    rows = ["| Вход | Выход |", "|------|-------|"]
    for i, o in tests:
        rows.append(f"| `{i.replace(chr(10), '`<br>`')}` | `{o.replace(chr(10), '`<br>`')}` |")
    return "\n".join(rows)


def source_md(r):
    name, url = r["source"]
    extra = f", {r['letter']}" if r["letter"] else ""
    return f"**Источник:** [{name}]({url}){extra}"


def block_md(b):
    out = [f"# Блок {b['num']}. {b['title']} (часы {b['hours']})", "", f"**Цель:** {b['goal']}", "",
           "## Лекция", ""]
    for head, lines in b["theory"]:
        out += [f"### {head}", ""]
        for line in lines:
            out += [line, ""]
    ex = b["example"]
    out += ["## Разобранный пример: " + ex["name"], "", ex["text"], "", tests_md(ex["tests"]), "",
            "**Разбор по шагам:**", ""]
    out += [f"{i}. {s}" for i, s in enumerate(ex["steps"], 1)]
    out += ["", "```python", code_of(ex["code"]).rstrip(), "```", "", "## Практика (самостоятельно)", ""]
    for t in b["practice"]:
        out += [f"### {t['name']}", "", t["text"], "", tests_md(t["tests"]), ""]
    out += ["## Реальные олимпиадные задачи", "",
            "Условия пересказаны кратко — точные форматы ввода/вывода и ограничения смотрите по ссылке.", ""]
    for r in b["real"]:
        out += [f"### {r['name']}", "", source_md(r), "", r["text"], "",
                f"**Идея решения.** {r['idea']}", ""]
        if r["code"]:
            out += [f"Эталонное решение: [`solutions/{r['code']}`](../solutions/{r['code']})", ""]
    out += ["## Для тех, кто решает быстрее", ""]
    for t in b["bonus"]:
        out += [f"### {t['name']}", "", t["text"], "", tests_md(t["tests"]), ""]
    out += ["---", "", "Подсказки и ответы — в [`otvety.md`](../otvety.md), код — в [`solutions/`](../solutions/)."]
    return "\n".join(out) + "\n"


def programma_md():
    out = [f"# {COURSE_TITLE}", "", COURSE_SUB, "",
           "Курс не повторяет темы муниципального варианта и первого курса: здесь перебор, строки, жадность, "
           "бинарный поиск, графы и динамика.", "", "## Расписание", "",
           "| Блок | Часы | Тема | Реальные задачи |", "|------|------|------|-----------------|"]
    for b in BLOCKS:
        out.append(f"| {b['num']} | {b['hours']} | {b['title']} | {', '.join(r['name'] for r in b['real'])} |")
    out += ["", "## Структура каждого 2-часового блока", "", "| Время | Что делаем |", "|-------|------------|"]
    out += [f"| {t} | {w} |" for t, w in TIMING]
    out += ["", "В блоке 6 последний час — мини-тур (см. [`mini-tur.md`](mini-tur.md)).", "",
            "## Цели блоков", ""]
    out += [f"{b['num']}. **{b['title']}** — {b['goal']}" for b in BLOCKS]
    out += ["", "## Как работать с быстрой группой", "",
            "- Учебные задачи практики — обязательный минимум; кто решил их, сразу переходит к реальным задачам блока.",
            "- Задачи раздела «Для тех, кто решает быстрее» — уровень выше среднего муниципального этапа.",
            "- Реальные задачи Codeforces можно сдавать на сайте с автоматической проверкой.",
            "- Задачи ВсОШ сдаются локально: примеры из PDF + свои тесты; эталонные решения есть в `solutions/`."]
    return "\n".join(out) + "\n"


def mini_md():
    m = MINI_TOUR
    out = [f"# {m['title']}", ""] + [f"- {r}" for r in m["rules"]] + [""]
    for t in m["tasks"]:
        out += [f"## {t['name']}", "", t["text"], "", tests_md(t["tests"]), ""]
    return "\n".join(out) + "\n"


def realnye_md():
    out = ["# Реальные олимпиадные задачи курса 2", "",
           "Все задачи взяты с муниципальных этапов ВсОШ (официальные архивы olimpiada.ru) и с Codeforces. "
           "Условия пересказаны кратко, полные условия — по ссылкам.", "",
           "| Блок | Задача | Источник |", "|------|--------|----------|"]
    for b in BLOCKS:
        for r in b["real"]:
            name, url = r["source"]
            extra = f", {r['letter']}" if r["letter"] else ""
            out.append(f"| {b['num']}. {b['title']} | {r['name']} | [{name}]({url}){extra} |")
    for b in BLOCKS:
        out += ["", f"## Блок {b['num']}. {b['title']}", ""]
        for r in b["real"]:
            out += [f"### {r['name']}", "", source_md(r), "", r["text"], "", f"**Идея.** {r['idea']}", ""]
    return "\n".join(out) + "\n"


def otvety_md():
    out = ["# Ответы и подсказки: практика, задачи для быстрых, мини-тур", "",
           "Все ответы на примерах проверены эталонными решениями из `solutions/` (`python3 build.py`, `python3 tests/run_tests.py`).", ""]
    for b in BLOCKS:
        out += [f"## Блок {b['num']}. {b['title']}", ""]
        for t in b["practice"] + b["bonus"]:
            out += [f"- **{t['name']}** — {t['hint']} Решение: `solutions/{t['code']}`."]
        out.append("")
    out += ["## Мини-тур", ""]
    out += [f"- **{t['name']}** — {t['hint']} Решение: `solutions/{t['code']}`." for t in MINI_TOUR["tasks"]]
    return "\n".join(out) + "\n"


def readme_md():
    out = [f"# {COURSE_TITLE}", "", COURSE_SUB, "", "## Содержание", "",
           "| Файл | Что внутри |", "|------|-----------|",
           "| [`programma.md`](programma.md) | расписание, тайминг блока, цели |"]
    for b in BLOCKS:
        out.append(f"| [`lekcii/{b['slug']}.md`](lekcii/{b['slug']}.md) | Блок {b['num']}: {b['title']} — "
                   f"лекция, разобранный пример с кодом, практика, реальные задачи, задачи для быстрых |")
    out += ["| [`mini-tur.md`](mini-tur.md) | мини-тур из 4 задач (последний час) |",
            "| [`realnye.md`](realnye.md) | все реальные задачи ВсОШ/Codeforces с источниками |",
            "| [`otvety.md`](otvety.md) | подсказки и ответы |",
            "| [`solutions/`](solutions/) | эталонные решения на Python ко всем задачам |",
            "| [`tests/run_tests.py`](tests/run_tests.py) | проверка решений (примеры + перебор) |",
            "| [`office/`](office/) | Word и PowerPoint |", "",
            "## Пересборка", "", "```bash", "python3 build.py              # Markdown + проверка примеров",
            "python3 tests/run_tests.py    # тесты решений", "python3 office/build_office.py  # Word и PowerPoint", "```"]
    return "\n".join(out) + "\n"


def main():
    n = check_examples()
    (ROOT / "lekcii").mkdir(exist_ok=True)
    for b in BLOCKS:
        (ROOT / "lekcii" / f"{b['slug']}.md").write_text(block_md(b), encoding="utf-8")
    (ROOT / "programma.md").write_text(programma_md(), encoding="utf-8")
    (ROOT / "mini-tur.md").write_text(mini_md(), encoding="utf-8")
    (ROOT / "realnye.md").write_text(realnye_md(), encoding="utf-8")
    (ROOT / "otvety.md").write_text(otvety_md(), encoding="utf-8")
    (ROOT / "README.md").write_text(readme_md(), encoding="utf-8")
    print(f"Markdown собран, проверено примеров: {n}")


if __name__ == "__main__":
    main()
