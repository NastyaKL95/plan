from collections import deque

n, m = map(int, input().split())
g = [input() for _ in range(n)]
seen = [[False] * m for _ in range(n)]
cnt = 0
for si in range(n):
    for sj in range(m):
        if g[si][sj] != "X" or seen[si][sj]:
            continue
        cnt += 1
        seen[si][sj] = True
        q = deque([(si, sj)])
        while q:
            i, j = q.popleft()
            for di, dj in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                a, b = i + di, j + dj
                if 0 <= a < n and 0 <= b < m and g[a][b] == "X" and not seen[a][b]:
                    seen[a][b] = True
                    q.append((a, b))
print(cnt)
