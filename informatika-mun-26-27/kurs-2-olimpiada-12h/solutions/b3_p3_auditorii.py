n = int(input())
events = []
for _ in range(n):
    l, r = map(int, input().split())
    events.append((l, 1))
    events.append((r, -1))
events.sort()
cur = best = 0
for _, d in events:
    cur += d
    best = max(best, cur)
print(best)
