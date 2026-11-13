#!/usr/bin/env python3
"""Проверка эталонных решений курса: примеры из условий + сравнение с перебором."""
import itertools
import random
import subprocess
import sys
from pathlib import Path

SOL = Path(__file__).resolve().parents[1] / "solutions"

EXAMPLES = [
    ("b1_primer_dva_kvadrata.py", "50", "2"),
    ("b1_primer_dva_kvadrata.py", "325", "3"),
    ("b1_primer_dva_kvadrata.py", "3", "0"),
    ("b1_p1_podmnozhestva.py", "4 5\n1 2 3 4", "2"),
    ("b1_p2_chasy.py", "00:00 23:59", "16"),
    ("b1_p2_chasy.py", "12:00 13:30", "1"),
    ("b1_p3_perestanovki.py", "4", "2"),
    ("b1_p3_perestanovki.py", "5", "14"),
    ("b1_d1_plyusy.py", "123 24", "1"),
    ("b1_d1_plyusy.py", "1111 13", "3"),
    ("b1_d2_troyki.py", "6\n-1 0 1 2 -1 -4", "3"),
    ("r_kubiki.py", "2\n7 5 6 8 6 7\n7 6 3 4 1 5", "yes\nno"),
    ("b2_primer_rle.py", "aaabccdddd", "a3b1c2d4"),
    ("b2_p1_anagrammy.py", "listen silent", "YES"),
    ("b2_p1_anagrammy.py", "abc abd", "NO"),
    ("b2_p2_stolbik.py", "999 1", "1000"),
    ("b2_p2_stolbik.py", "123456789123456789 987654321987654321", "1111111111111111110"),
    ("b2_p3_blok.py", "aabbbbcc", "b 4"),
    ("b2_d1_palindrom.py", "aabbc", "abcba"),
    ("b2_d1_palindrom.py", "abc", "NO"),
    ("b2_d2_sistemy.py", "FF 16 2", "11111111"),
    ("b2_d2_sistemy.py", "255 10 16", "FF"),
    ("r_lunnaya.py", "169\n248", "269 12468"),
    ("r_lunnaya.py", "54\n321", "354 3321"),
    ("r_magicheskie.py", "magic", "0"),
    ("r_magicheskie.py", "filth", "1"),
    ("b3_primer_zayavki.py", "5\n1 3\n2 5\n4 6\n6 8\n5 7", "3"),
    ("b3_p1_lodki.py", "4 100\n50 50 70 30", "2"),
    ("b3_p1_lodki.py", "3 10\n9 8 7", "3"),
    ("b3_p2_pary.py", "4 5\n1 2 3 4", "4"),
    ("b3_p3_auditorii.py", "3\n1 4\n2 5\n4 6", "2"),
    ("b3_d1_ochered.py", "3\n3 1 2", "10"),
    ("b3_d2_cf1324d.py", "5\n4 8 2 6 2\n4 5 4 1 3", "7"),
    ("b3_d2_cf1324d.py", "4\n1 3 2 4\n1 3 2 4", "0"),
    ("cf279b_books.py", "4 5\n3 1 2 1", "3"),
    ("cf279b_books.py", "3 3\n2 2 3", "1"),
    ("b4_primer_kserokopii.py", "4 1 1", "3"),
    ("b4_primer_kserokopii.py", "5 1 2", "4"),
    ("b4_p1_otrezok.py", "6\n5 1 3 3 8 10\n2\n3 8\n11 20", "4\n0"),
    ("b4_p2_verevki.py", "4 11\n802 743 457 539", "200"),
    ("b4_p3_nok.py", "3\n4 6 10", "2 60"),
    ("b4_d1_uravnenie.py", "10", "2.000000"),
    ("b4_d2_cf448d.py", "2 2 2", "2"),
    ("b4_d2_cf448d.py", "2 3 4", "3"),
    ("b4_d2_cf448d.py", "1 10 5", "5"),
    ("cf474b_worms.py", "5\n2 7 3 4 9\n3\n1 25 11", "1\n5\n3"),
    ("cf1201c_median.py", "3 2\n1 3 5", "5"),
    ("cf1201c_median.py", "5 5\n1 2 1 1 1", "3"),
    ("cf1201c_median.py", "7 7\n4 1 2 4 3 4 4", "5"),
    ("r_derevo.py", "3 4", "RLRR"),
    ("r_derevo.py", "4 3", "RRLL"),
    ("r_derevo.py", "8 6", "RRLL"),
    ("b5_primer_labirint.py", "4 5\nS..#.\n.#...\n.#.#F\n...#.", "6"),
    ("b5_p1_komponenty.py", "6 3\n1 2\n2 3\n5 6", "3"),
    ("b5_p2_put.py", "5 5 1 5\n1 2\n2 3\n3 5\n1 4\n4 3", "3\n1 2 3 5"),
    ("b5_p3_pozhar.py", "3 4\nF...\n.##.\n...F", "2"),
    ("b5_d1_kon.py", "8\n1 1 8 8", "6"),
    ("b5_d2_steny_01bfs.py", "3 3\n.#.\n##.\n...", "1"),
    ("cf520b_buttons.py", "4 6", "2"),
    ("cf520b_buttons.py", "10 1", "9"),
    ("r_ohrana.py", "5 5 2\n1 2\n2 3\n2 4\n3 5\n1 5\n1 2\n5 1", "5\n1 2 3 4 5"),
    ("r_ohrana.py", "3 0 1\n1 3", "1\n1"),
    ("r_ohrana.py", "10 10 2\n1 2\n5 1\n1 6\n2 4\n5 2\n10 2\n8 5\n8 6\n9 6\n7 9\n3 2\n8 1", "4\n3 5 6 8"),
    ("b6_primer_kuznechik.py", "6\n0 3 2 7 1 4", "7"),
    ("b6_p1_lestnica.py", "4", "7"),
    ("b6_p1_lestnica.py", "10", "274"),
    ("b6_p2_cherepashka.py", "3 3\n1 3 1\n1 5 1\n4 2 1", "12"),
    ("b6_p3_nvp.py", "8\n3 1 4 1 5 9 2 6", "4"),
    ("b6_d1_ryukzak.py", "4 7\n3 4\n4 5\n2 3\n5 8", "11"),
    ("b6_d2_puti.py", "3 3\n...\n.#.\n...", "2"),
    ("cf189a_ribbon.py", "5 5 3 2", "2"),
    ("cf189a_ribbon.py", "7 5 5 2", "2"),
    ("cf455a_boredom.py", "3\n1 2 3", "4"),
    ("cf455a_boredom.py", "9\n1 2 1 3 2 2 2 2 3", "10"),
    ("m1_plitka.py", "6 6 4", "4"),
    ("m1_plitka.py", "10 3 3", "4"),
    ("m2_dubli.py", "abbaca", "ca"),
    ("m2_dubli.py", "aabb", "-"),
    ("m3_summa_s.py", "5 5\n1 2 3 4 5", "2"),
    ("m4_ostrova.py", "4 5\nXX..X\nX...X\n..X..\n.....", "3"),
]


def run(script, stdin):
    r = subprocess.run([sys.executable, str(SOL / script)], input=stdin + "\n",
                       text=True, capture_output=True, check=True)
    return r.stdout.strip()


def random_checks():
    rnd = random.Random(2026)
    for _ in range(60):
        n = rnd.randint(1, 7)
        c = rnd.randint(5, 20)
        a = [rnd.randint(1, c) for _ in range(n)]
        best = n
        for perm in itertools.permutations(a):
            boats, i = 0, 0
            while i < n:
                if i + 1 < n and perm[i] + perm[i + 1] <= c:
                    i += 2
                else:
                    i += 1
                boats += 1
            best = min(best, boats)
        assert run("b3_p1_lodki.py", f"{n} {c}\n{' '.join(map(str, a))}") == str(best)

        s = rnd.randint(1, 20)
        exp = sum(1 for i in range(n) for j in range(i + 1, n) if a[i] + a[j] <= s)
        assert run("b3_p2_pary.py", f"{n} {s}\n{' '.join(map(str, a))}") == str(exp)

        exp = sum(1 for i in range(n) for j in range(i, n) if sum(a[i:j + 1]) == s)
        assert run("m3_summa_s.py", f"{n} {s}\n{' '.join(map(str, a))}") == str(exp)

        b = [rnd.randint(-5, 5) for _ in range(n)]
        exp = sum(1 for t in itertools.combinations(b, 3) if sum(t) == 0)
        assert run("b1_d2_troyki.py", f"{n}\n{' '.join(map(str, b))}") == str(exp)

        k, x, y = rnd.randint(1, 15), rnd.randint(1, 5), rnd.randint(1, 5)
        t = 0
        while True:
            if t >= min(x, y) and (t - min(x, y)) // x + (t - min(x, y)) // y >= k - 1:
                break
            t += 1
        assert run("b4_primer_kserokopii.py", f"{k} {x} {y}") == str(t)

        segs = sorted((l, l + rnd.randint(1, 4)) for l in (rnd.randint(0, 10) for _ in range(n)))
        best = 0
        for mask in range(1 << n):
            ch = sorted(segs[i] for i in range(n) if mask >> i & 1)
            if all(ch[i][1] <= ch[i + 1][0] for i in range(len(ch) - 1)):
                best = max(best, len(ch))
        inp = f"{n}\n" + "\n".join(f"{l} {r}" for l, r in segs)
        assert run("b3_primer_zayavki.py", inp) == str(best)


def check_morskoy_boy(rnd):
    for _ in range(80):
        coords = rnd.sample(range(-20, 21, 2), rnd.randint(2, 8))
        n = rnd.randint(1, len(coords) - 1)
        left, right = sorted(coords[:n]), sorted(coords[n:])
        inp = f"{n}\n{len(right)}\n" + "\n".join(map(str, left + right))
        shots = [tuple(map(int, line.split())) for line in run("r_morskoy_boy.py", inp).splitlines()]
        alive = {("L", x) for x in left} | {("R", x) for x in right}
        for t, x in shots:
            alive -= {("L", x + t)} if ("L", x + t) in alive else set()
            alive -= {("R", x - t)} if ("R", x - t) in alive else set()
        assert not alive, (inp, shots)
        match = {}

        def kuhn(ri, seen):
            for li, l in enumerate(left):
                if l > right[ri] and li not in seen:
                    seen.add(li)
                    if li not in match or kuhn(match[li], seen):
                        match[li] = ri
                        return True
            return False

        pairs = sum(kuhn(ri, set()) for ri in range(len(right)))
        assert len(shots) == n + len(right) - pairs


def check_sezd(rnd):
    for _ in range(3):
        n = rnd.randint(1, 6)
        parts = [rnd.sample(range(1, 13), 3) for _ in range(n)]
        inp = f"{n}\n" + "\n".join(" ".join(map(str, q)) for q in parts)
        rows = [list(map(int, line.split())) for line in run("r_sezd.py", inp).splitlines()]
        assert sorted(sum(rows, [])) == list(range(1, 13)) and all(len(r) == 4 for r in rows)
        day = {x: d for d, r in enumerate(rows) for x in r}
        got = sum(len({day[x] for x in q}) for q in parts)
        best = 0
        for assign in itertools.product(range(3), repeat=12):
            if assign.count(0) == 4 and assign.count(1) == 4:
                best = max(best, sum(len({assign[x - 1] for x in q}) for q in parts))
        assert got == best


def main():
    for script, stdin, expected in EXAMPLES:
        got = run(script, stdin)
        assert got == expected, (script, stdin, got, expected)
    random_checks()
    check_morskoy_boy(random.Random(1))
    check_sezd(random.Random(5))
    assert run("r_morskoy_boy.py", "2\n1\n10\n30\n20").count("\n") == 1
    print(f"OK: {len(EXAMPLES)} примеров + случайные проверки с перебором")


if __name__ == "__main__":
    main()
