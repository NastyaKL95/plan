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
