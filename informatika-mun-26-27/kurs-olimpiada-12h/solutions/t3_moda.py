from collections import Counter

n = int(input())
a = list(map(int, input().split()))
cnt = Counter(a)
best_c, best_v = -1, 10**18
for v, c in cnt.items():
    if c > best_c or (c == best_c and v < best_v):
        best_c, best_v = c, v
print(best_c, best_v)
