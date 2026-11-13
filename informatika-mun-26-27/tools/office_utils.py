"""Общие помощники для сборки Word/PowerPoint: формулы LaTeX → Unicode, Markdown → docx, шаблоны слайдов."""
from __future__ import annotations

import re
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Cm, Pt, RGBColor as DocxRGB
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.util import Inches, Pt as PptPt

ACCENT = RGBColor(0x1F, 0x4E, 0x79)
ACCENT2 = RGBColor(0x2E, 0x7D, 0x32)
INK = RGBColor(0x1C, 0x1A, 0x17)
MUTED = RGBColor(0x5C, 0x57, 0x4F)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
CODE_BG = RGBColor(0xF3, 0xF1, 0xEC)

_SUP = str.maketrans("0123456789-+()n", "⁰¹²³⁴⁵⁶⁷⁸⁹⁻⁺⁽⁾ⁿ")

_SIMPLE = [
    (r"\le", "≤"), (r"\ge", "≥"), (r"\cdot", "·"), (r"\dots", "…"), (r"\times", "×"),
    (r"\lfloor", "⌊"), (r"\rfloor", "⌋"), (r"\lceil", "⌈"), (r"\rceil", "⌉"),
    (r"\left", ""), (r"\right", ""), (r"\bmod", "mod"), (r"\mathbin{//}", "//"),
    (r"\text{lcm}", "lcm"), (r"\text{Ответ}", "Ответ"), (r"\gcd", "gcd"), (r"\log_2", "log₂"),
    (r"\log", "log"), (r"\min", "min"), (r"\max", "max"), (r"\approx", "≈"), (r"\quad", "   "),
    (r"\{", "{"), (r"\}", "}"), (r"\,", " "), (r"\neq", "≠"),
]


def plain(text: str) -> str:
    """Переводит LaTeX-формулы из Markdown в читаемый Unicode-текст."""
    s = text.replace(r"\(", "").replace(r"\)", "").replace(r"\[", "").replace(r"\]", "")
    s = re.sub(r"C_\{(\d+)\}\^(\d+)", r"C(\1, \2)", s)
    s = re.sub(r"\\sum_\{([^}]*)\}\^\{([^}]*)\}", r"Σ[\1..\2]", s)
    for _ in range(3):
        s = re.sub(r"\\t?frac\{([^{}]*)\}\{([^{}]*)\}", r"(\1)/(\2)", s)
    s = re.sub(r"\\sqrt\{([^{}]*)\}", r"√(\1)", s)
    s = s.replace(r"\sqrt ", "√")
    for k, v in _SIMPLE:
        s = s.replace(k, v)
    s = re.sub(r"\^\{([0-9n+\-()]+)\}", lambda m: m.group(1).translate(_SUP), s)
    s = re.sub(r"\^([0-9n])", lambda m: m.group(1).translate(_SUP), s)
    s = re.sub(r"\^\{([^}]*)\}", r"^(\1)", s)
    s = re.sub(r"_\{([^}]*)\}", r"_\1", s)
    s = s.replace("\\", "")
    return re.sub(r" {2,}", " ", s) if "   " not in text else s


# ------------------------------------------------------------------ Word

def new_doc() -> Document:
    doc = Document()
    st = doc.styles["Normal"]
    st.font.name = "Times New Roman"
    st.font.size = Pt(12)
    for sec in doc.sections:
        sec.top_margin, sec.bottom_margin = Cm(2), Cm(2)
        sec.left_margin, sec.right_margin = Cm(2.5), Cm(2)
    return doc


def rich(par, text: str, bold=False, size=12):
    """Добавляет в абзац текст с разметкой **жирный** и `код`."""
    text = plain(text)
    for part in re.split(r"(\*\*[^*]+\*\*|`[^`]+`)", text):
        if not part:
            continue
        if part.startswith("**"):
            r = par.add_run(part[2:-2])
            r.bold = True
        elif part.startswith("`"):
            r = par.add_run(part[1:-1])
            r.font.name = "Consolas"
            r.font.color.rgb = DocxRGB(0x8B, 0x1A, 0x1A)
        else:
            r = par.add_run(part)
            r.bold = bold
        r.font.size = Pt(size)
    return par


def d_title(doc, text, sub=None):
    p = doc.add_heading(text, 0)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    if sub:
        para = doc.add_paragraph()
        para.alignment = WD_ALIGN_PARAGRAPH.CENTER
        rich(para, sub, size=12)


def d_h(doc, text, level=1):
    doc.add_heading(plain(text).replace("**", ""), level)


def d_p(doc, text, bold=False):
    return rich(doc.add_paragraph(), text, bold=bold)


def d_bullets(doc, items, style="List Bullet"):
    for it in items:
        rich(doc.add_paragraph(style=style), it)


def d_code(doc, code: str):
    for line in code.rstrip("\n").split("\n"):
        para = doc.add_paragraph()
        para.paragraph_format.space_after = Pt(0)
        para.paragraph_format.left_indent = Cm(0.6)
        r = para.add_run(line if line else " ")
        r.font.name = "Consolas"
        r.font.size = Pt(10)
    doc.add_paragraph()


def d_table(doc, headers, rows):
    t = doc.add_table(rows=1 + len(rows), cols=len(headers))
    t.style = "Table Grid"
    for i, x in enumerate(headers):
        cell = t.rows[0].cells[i]
        cell.text = ""
        rich(cell.paragraphs[0], x, bold=True, size=11)
    for ri, row in enumerate(rows):
        for ci, val in enumerate(row):
            cell = t.rows[ri + 1].cells[ci]
            cell.text = ""
            lines = str(val).split("\n")
            for li, line in enumerate(lines):
                para = cell.paragraphs[0] if li == 0 else cell.add_paragraph()
                if "`" in line or "**" in line:
                    rich(para, line, size=11)
                else:
                    r = para.add_run(plain(line))
                    r.font.size = Pt(11)
                    if list(headers[:2]) == ["Вход", "Выход"] and ci < 2:
                        r.font.name = "Consolas"
    doc.add_paragraph()


def d_tests(doc, tests):
    d_table(doc, ["Вход", "Выход"], [[i, o] for i, o in tests])


def md_to_docx(md_path: Path, doc: Document, skip_first_h1=False):
    """Простой конвертер Markdown (заголовки, абзацы, списки, таблицы, код) в docx."""
    lines = md_path.read_text(encoding="utf-8").split("\n")
    i, first = 0, True
    while i < len(lines):
        line = lines[i]
        if line.startswith("```"):
            j = i + 1
            while not lines[j].startswith("```"):
                j += 1
            d_code(doc, "\n".join(lines[i + 1:j]))
            i = j + 1
            continue
        if line.startswith("|"):
            block = []
            while i < len(lines) and lines[i].startswith("|"):
                block.append(lines[i])
                i += 1
            cells = [[c.strip().replace("<br>", "\n").replace("`", "") for c in r.strip("|").split("|")]
                     for r in block if not re.match(r"^\|[\s\-|:]+\|$", r)]
            d_table(doc, cells[0], cells[1:])
            continue
        m = re.match(r"^(#+)\s+(.*)", line)
        if m:
            level = len(m.group(1))
            if level == 1 and first:
                first = False
                if skip_first_h1:
                    i += 1
                    continue
                d_title(doc, m.group(2))
            else:
                d_h(doc, m.group(2), min(level - 1, 3) or 1)
        elif line.strip() == "---" or not line.strip():
            pass
        elif re.match(r"^\s*[-*] ", line):
            rich(doc.add_paragraph(style="List Bullet"), re.sub(r"^\s*[-*] ", "", line))
        elif re.match(r"^\d+\. ", line):
            rich(doc.add_paragraph(style="List Number"), re.sub(r"^\d+\. ", "", line))
        else:
            text = line
            while i + 1 < len(lines) and lines[i + 1].strip() and not re.match(r"^(#|\||```|\s*[-*] |\d+\. |---)", lines[i + 1]):
                i += 1
                text += " " + lines[i].strip()
            d_p(doc, text.rstrip(" \\"))
        i += 1


# ------------------------------------------------------------------ PowerPoint

def new_prs() -> Presentation:
    prs = Presentation()
    prs.slide_width = Inches(13.333)
    prs.slide_height = Inches(7.5)
    return prs


def _bar(slide, prs, color=ACCENT, height=1.15):
    shp = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, prs.slide_width, Inches(height))
    shp.fill.solid()
    shp.fill.fore_color.rgb = color
    shp.line.fill.background()
    return shp


def _text(slide, x, y, w, h, text, size=20, color=INK, bold=False, font=None):
    tb = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    tf = tb.text_frame
    tf.word_wrap = True
    first = True
    for line in text.split("\n"):
        para = tf.paragraphs[0] if first else tf.add_paragraph()
        first = False
        r = para.add_run()
        r.text = line
        r.font.size = PptPt(size)
        r.font.color.rgb = color
        r.font.bold = bold
        if font:
            r.font.name = font
    return tb


def s_title(prs, title, sub):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    bg = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, prs.slide_width, prs.slide_height)
    bg.fill.solid()
    bg.fill.fore_color.rgb = ACCENT
    bg.line.fill.background()
    _text(s, 0.8, 2.3, 11.7, 1.8, plain(title), 40, WHITE, True)
    _text(s, 0.8, 4.3, 11.7, 1.6, plain(sub), 22, WHITE)
    return s


def s_section(prs, label, name, line):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    bg = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, 0, 0, Inches(4.2), prs.slide_height)
    bg.fill.solid()
    bg.fill.fore_color.rgb = ACCENT
    bg.line.fill.background()
    _text(s, 0.4, 2.8, 3.4, 1.5, label, 54, WHITE, True)
    _text(s, 4.8, 2.4, 8.0, 1.4, plain(name), 38, ACCENT, True)
    _text(s, 4.8, 3.9, 8.0, 2.0, plain(line), 20, MUTED)
    return s


def _clean(line: str) -> str:
    return plain(line).replace("**", "").replace("`", "")


def s_content(prs, title, lines, foot=None, size=20, color=ACCENT):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    _bar(s, prs, color)
    _text(s, 0.5, 0.22, 12.3, 0.8, plain(title), 30, WHITE, True)
    tb = s.shapes.add_textbox(Inches(0.6), Inches(1.45), Inches(12.1), Inches(5.3))
    tf = tb.text_frame
    tf.word_wrap = True
    first = True
    for line in lines:
        para = tf.paragraphs[0] if first else tf.add_paragraph()
        first = False
        bullet = line.startswith("- ")
        txt = _clean(line[2:] if bullet else line)
        r = para.add_run()
        r.text = ("•  " + txt) if bullet else txt
        r.font.size = PptPt(size - 1 if bullet else size)
        r.font.color.rgb = INK
        para.space_after = PptPt(6)
    if foot:
        _text(s, 0.6, 6.85, 12.1, 0.5, _clean(foot), 14, MUTED)
    return s


def s_code(prs, title, code, note=None, size=14):
    s = prs.slides.add_slide(prs.slide_layouts[6])
    _bar(s, prs)
    _text(s, 0.5, 0.22, 12.3, 0.8, plain(title), 28, WHITE, True)
    width = 12.1 if not note else 7.6
    box = s.shapes.add_shape(MSO_SHAPE.RECTANGLE, Inches(0.5), Inches(1.4), Inches(width), Inches(5.8))
    box.fill.solid()
    box.fill.fore_color.rgb = CODE_BG
    box.line.fill.background()
    n_lines = code.count("\n") + 1
    fs = size if n_lines <= 22 else max(9, int(size * 22 / n_lines))
    _text(s, 0.65, 1.5, width - 0.3, 5.6, code.rstrip("\n"), fs, INK, font="Consolas")
    if note:
        _text(s, 8.4, 1.5, 4.5, 5.6, "\n\n".join(_clean(x) for x in note), 16, INK)
    return s
