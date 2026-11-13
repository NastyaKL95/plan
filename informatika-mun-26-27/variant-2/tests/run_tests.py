#!/usr/bin/env python3
"""Проверка эталонных решений варианта 2: примеры + сравнение с перебором."""
import random
import subprocess
import sys
from pathlib import Path

SOL = Path(__file__).resolve().parents[1] / "solutions"
MOD = 10**9 + 7


def run(script, stdin):
    r = subprocess.run([sys.executable, str(SOL / script)], input=stdin + "\n",
                       text=True, capture_output=True, check=True)
    return r.stdout.strip()


def brute_rows(n, a, b):
    p = 1
    while 2 * a + (p - 1) * b < n:
        p += 1
    return p


def brute_code(n, k):
    best = -1
    for x in range(1, 10**k):
        s = str(x)
        if "0" in s:
            continue
        prod = 1
        for c in s:
            prod *= int(c)
        if prod == n:
            best = x
    return best


def brute_squares(n, m):
    pts = {(x, y) for x in range(n + 1) for y in range(m + 1)}
    cnt = 0
    for x, y in pts:
        for dx in range(n + 1):
            for dy in range(1, n + m + 1):
                if (x + dx, y + dy) in pts and (x + dx - dy, y + dy + dx) in pts and (x - dy, y + dx) in pts:
                    cnt += 1
    return cnt


def main():
    examples = [
        ("01_ryady.py", "10 3 4", "2"), ("01_ryady.py", "11 2 5", "3"), ("01_ryady.py", "4 2 5", "1"),
        ("01_ryady.py", "1000000000000000000 1 1", "999999999999999999"),
        ("02_maks_nomer.py", "70 3", "752"), ("02_maks_nomer.py", "70 5", "75211"),
        ("02_maks_nomer.py", "36 2", "94"), ("02_maks_nomer.py", "100 2", "-1"),
        ("02_maks_nomer.py", "1 3", "111"), ("02_maks_nomer.py", "101 4", "-1"),
        ("03_otrezki.py", "5 3\n1 2 3 4 5", "7"), ("03_otrezki.py", "4 2\n-1 1 -1 1", "4"),
        ("04_kvadraty.py", "1\n1", "1"), ("04_kvadraty.py", "2\n2", "6"),
        ("04_kvadraty.py", "3\n3", "20"), ("04_kvadraty.py", "2\n5", "18"),
    ]
    for script, stdin, exp in examples:
        got = run(script, stdin)
        assert got == exp, (script, stdin, got, exp)

    rnd = random.Random(7)
    for _ in range(40):
        n, a, b = rnd.randint(1, 60), rnd.randint(1, 6), rnd.randint(1, 6)
        assert run("01_ryady.py", f"{n} {a} {b}") == str(brute_rows(n, a, b))
    for k in (1, 2, 3):
        for n in rnd.sample(range(1, 800), 25) + [64, 72, 81]:
            assert run("02_maks_nomer.py", f"{n} {k}") == str(brute_code(n, k)), (n, k)
    for _ in range(60):
        n, k = rnd.randint(1, 12), rnd.randint(1, 8)
        arr = [rnd.randint(-9, 9) for _ in range(n)]
        exp = sum(1 for l in range(n) for r in range(l, n) if sum(arr[l:r + 1]) % k == 0)
        assert run("03_otrezki.py", f"{n} {k}\n{' '.join(map(str, arr))}") == str(exp)
    for n in range(1, 6):
        for m in range(1, 6):
            assert run("04_kvadraty.py", f"{n}\n{m}") == str(brute_squares(n, m) % MOD)
    print("OK: вариант 2 — примеры и сравнение с перебором")


if __name__ == "__main__":
    main()
