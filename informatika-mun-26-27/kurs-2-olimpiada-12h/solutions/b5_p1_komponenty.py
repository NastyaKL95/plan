from collections import deque

n, m = map(int, input().split())
g = [[] for _ in range(n + 1)]
for _ in range(m):
    u, v = map(int, input().split())
    g[u].append(v)
    g[v].append(u)
seen = [False] * (n + 1)
comps = 0
for s in range(1, n + 1):
    if seen[s]:
        continue
    comps += 1
    seen[s] = True
    q = deque([s])
    while q:
        v = q.popleft()
        for u in g[v]:
            if not seen[u]:
                seen[u] = True
                q.append(u)
print(comps)
