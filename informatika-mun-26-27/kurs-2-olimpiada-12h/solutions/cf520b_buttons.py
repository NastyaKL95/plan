from collections import deque

n, m = map(int, input().split())
LIM = 2 * max(n, m) + 2
dist = [-1] * (LIM + 1)
dist[n] = 0
q = deque([n])
while q:
    v = q.popleft()
    for u in (v * 2, v - 1):
        if 1 <= u <= LIM and dist[u] == -1:
            dist[u] = dist[v] + 1
            q.append(u)
print(dist[m])
