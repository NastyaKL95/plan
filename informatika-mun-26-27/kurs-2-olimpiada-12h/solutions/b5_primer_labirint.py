from collections import deque

n, m = map(int, input().split())
g = [input() for _ in range(n)]
for i in range(n):
    for j in range(m):
        if g[i][j] == "S":
            start = (i, j)
dist = [[-1] * m for _ in range(n)]
dist[start[0]][start[1]] = 0
q = deque([start])
ans = -1
while q:
    i, j = q.popleft()
    if g[i][j] == "F":
        ans = dist[i][j]
        break
    for di, dj in ((1, 0), (-1, 0), (0, 1), (0, -1)):
        a, b = i + di, j + dj
        if 0 <= a < n and 0 <= b < m and g[a][b] != "#" and dist[a][b] == -1:
            dist[a][b] = dist[i][j] + 1
            q.append((a, b))
print(ans)
