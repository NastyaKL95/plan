from collections import deque

n, m, s, t = map(int, input().split())
g = [[] for _ in range(n + 1)]
for _ in range(m):
    u, v = map(int, input().split())
    g[u].append(v)
    g[v].append(u)
par = [0] * (n + 1)
dist = [-1] * (n + 1)
dist[s] = 0
q = deque([s])
while q:
    v = q.popleft()
    for u in sorted(g[v]):
        if dist[u] == -1:
            dist[u] = dist[v] + 1
            par[u] = v
            q.append(u)
if dist[t] == -1:
    print(-1)
else:
    path = [t]
    while path[-1] != s:
        path.append(par[path[-1]])
    print(dist[t])
    print(*reversed(path))
