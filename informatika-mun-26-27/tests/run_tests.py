#!/usr/bin/env python3
"""Простые тесты эталонных решений."""
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOL = ROOT / "solutions"


def run(script: str, stdin: str) -> str:
    r = subprocess.run(
        [sys.executable, str(SOL / script)],
        input=stdin,
        text=True,
        capture_output=True,
        check=True,
    )
    return r.stdout.strip()


def main() -> None:
    assert run("01_kinoteatr.py", "4\n") == "1"
    assert run("01_kinoteatr.py", "5\n") == "2"
    assert run("01_kinoteatr.py", "10\n") == "3"
    assert run("01_kinoteatr.py", "15\n") == "4"

    assert run("02_chetnyy_artikul.py", "70\n") == "572"
    assert run("02_chetnyy_artikul.py", "16\n") == "28"
    assert run("02_chetnyy_artikul.py", "36\n") == "94"
    assert run("02_chetnyy_artikul.py", "101\n") == "-1"
    assert run("02_chetnyy_artikul.py", "7\n") == "-1"
    assert run("02_chetnyy_artikul.py", "8\n") == "8"

    assert run("03_truby.py", "5\n1 2 3 4 5\n") == "7 1"
    assert run("03_truby.py", "3\n3 6 12\n") == "7 3"
    assert run("03_truby.py", "3\n8 8 8\n") == "24 1"
    assert run("03_truby.py", "1\n7\n") == "1 7"

    assert run("04_katety.py", "1\n1\n") == "4"
    assert run("04_katety.py", "1\n2\n") == "10"
    assert run("04_katety.py", "2\n2\n") == "28"
    assert run("04_katety.py", "3\n5\n") == "156"
    assert run("04_katety.py", "5\n5\n") == "312"

    print("All tests passed")


if __name__ == "__main__":
    main()
