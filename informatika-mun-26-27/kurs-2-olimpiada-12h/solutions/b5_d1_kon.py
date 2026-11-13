from collections import deque

n = int(input())
x1, y1, x2, y2 = map(int, input().split())
dist = [[-1] * (n + 1) for _ in range(n + 1)]
dist[x1][y1] = 0
q = deque([(x1, y1)])
moves = [(1, 2), (2, 1), (-1, 2), (-2, 1), (1, -2), (2, -1), (-1, -2), (-2, -1)]
while q:
    x, y = q.popleft()
    for dx, dy in moves:
        a, b = x + dx, y + dy
        if 1 <= a <= n and 1 <= b <= n and dist[a][b] == -1:
            dist[a][b] = dist[x][y] + 1
            q.append((a, b))
print(dist[x2][y2])
