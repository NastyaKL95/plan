from collections import deque

n, m = map(int, input().split())
g = [input() for _ in range(n)]
INF = 10**9
dist = [[INF] * m for _ in range(n)]
dist[0][0] = 1 if g[0][0] == "#" else 0
q = deque([(0, 0)])
while q:
    i, j = q.popleft()
    for di, dj in ((1, 0), (-1, 0), (0, 1), (0, -1)):
        a, b = i + di, j + dj
        if 0 <= a < n and 0 <= b < m:
            w = 1 if g[a][b] == "#" else 0
            if dist[i][j] + w < dist[a][b]:
                dist[a][b] = dist[i][j] + w
                if w:
                    q.append((a, b))
                else:
                    q.appendleft((a, b))
print(dist[n - 1][m - 1])
