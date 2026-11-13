import sys
from collections import Counter
from itertools import combinations

data = list(map(int, sys.stdin.read().split()))
n = data[0]
triples = Counter(tuple(sorted(data[1 + 3 * i: 4 + 3 * i])) for i in range(n))

best, best_plan = -1, None
rest = list(range(2, 13))
for day1_rest in combinations(rest, 3):
    day1 = (1,) + day1_rest
    left = [x for x in rest if x not in day1_rest]
    first = left[0]
    for day2_rest in combinations(left[1:], 3):
        day2 = (first,) + day2_rest
        day3 = tuple(x for x in left if x not in day2)
        day = [0] * 13
        for d, group in enumerate((day1, day2, day3)):
            for x in group:
                day[x] = d
        score = sum(c * len({day[a], day[b], day[e]}) for (a, b, e), c in triples.items())
        if score > best:
            best, best_plan = score, (day1, day2, day3)
for group in best_plan:
    print(*group)
