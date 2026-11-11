# Разбор и эталонные решения

## № 1. Кинотеатр

При \(p \ge 1\) проходах образуется \(p+1\) групп кресел: две крайние (≤ 2) и \(p-1\) средних (≤ 5).

Максимум кресел: \(2\cdot 2 + 5(p-1) = 4 + 5(p-1)\).

Нужно минимальное \(p \ge 1\) с \(4 + 5(p-1) \ge n\):

- если \(n \le 4\), ответ \(1\);
- иначе \(p = 1 + \lceil (n-4)/5 \rceil\).

```python
n = int(input())
if n <= 4:
    print(1)
else:
    print(1 + (n - 4 + 5 - 1) // 5)
```

---

## № 2. Чётный артикул

1. Жадное разложение \(N\) на множители-цифры от 9 к 2 даёт набор цифр минимальной длины (как в классической задаче).
2. Если разложить нельзя — ответ `-1`.
3. Если среди цифр нет чётной — произведение нечётно либо число нельзя сделать чётным без смены набора → `-1`.
4. Иначе перебираем, какую чётную цифру поставить в конец, а остальные сортируем по возрастанию; выбираем минимальное число.

```python
from collections import Counter

N = int(input())
if N == 1:
    print(-1)
    raise SystemExit

n = N
digits = []
for d in range(9, 1, -1):
    while n % d == 0:
        n //= d
        digits.append(d)

if n != 1:
    print(-1)
    raise SystemExit

evens = [d for d in digits if d % 2 == 0]
if not evens:
    print(-1)
    raise SystemExit

base = Counter(digits)
best = None
for last in set(evens):
    cnt = base.copy()
    cnt[last] -= 1
    rest = []
    for d in range(2, 10):
        rest += [d] * cnt[d]
    rest.sort()
    cand = int("".join(map(str, rest)) + str(last)) if rest else last
    if best is None or cand < best:
        best = cand
print(best)
```

---

## № 3. Трубы

Любую трубу длины \(a = d \cdot 2^k\) (\(d\) нечётно) можно полностью «разобрать» на \(2^k\) труб длины \(d\).

Для каждого нечётного \(d\) суммируем вклад всех труб. Ответ — максимум суммы; при равенстве — меньшее \(d\).

```python
from collections import defaultdict

n = int(input())
a = list(map(int, input().split()))
pieces = defaultdict(int)
for x in a:
    k = 0
    while x % 2 == 0:
        x //= 2
        k += 1
    pieces[x] += 1 << k

best_c, best_d = -1, 10**18
for d, c in pieces.items():
    if c > best_c or (c == best_c and d < best_d):
        best_c, best_d = c, d
print(best_c, best_d)
```

---

## № 4. Катеты на сетке

Фиксируем длину катета \(k \in \{1,2,3\}\).

**Осевые катеты** (4 ориентации):  
\(4 \cdot \max(0, n-k+1) \cdot \max(0, m-k+1)\).

**Диагональные катеты** (4 пары перпендикулярных диагональных направлений):  
\(2 \cdot \max(0, n-2k+1) \cdot \max(0, m-k+1) + 2 \cdot \max(0, n-k+1) \cdot \max(0, m-2k+1)\).

Суммируем по \(k = 1,2,3\). При \(n,m \le 10^8\) достаточно 64-битной арифметики (в Python — обычные `int`).

```python
n = int(input())
m = int(input())
ans = 0
for k in (1, 2, 3):
    ans += 4 * max(0, n - k + 1) * max(0, m - k + 1)
    ans += 2 * max(0, n - 2 * k + 1) * max(0, m - k + 1)
    ans += 2 * max(0, n - k + 1) * max(0, m - 2 * k + 1)
print(ans)
```
