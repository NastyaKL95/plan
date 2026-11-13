from collections import deque

n, m = map(int, input().split())
g = [input() for _ in range(n)]
dist = [[-1] * m for _ in range(n)]
q = deque()
for i in range(n):
    for j in range(m):
        if g[i][j] == "F":
            dist[i][j] = 0
            q.append((i, j))
while q:
    i, j = q.popleft()
    for di, dj in ((1, 0), (-1, 0), (0, 1), (0, -1)):
        a, b = i + di, j + dj
        if 0 <= a < n and 0 <= b < m and g[a][b] != "#" and dist[a][b] == -1:
            dist[a][b] = dist[i][j] + 1
            q.append((a, b))
cells = [dist[i][j] for i in range(n) for j in range(m) if g[i][j] != "#"]
print(-1 if -1 in cells else max(cells))
