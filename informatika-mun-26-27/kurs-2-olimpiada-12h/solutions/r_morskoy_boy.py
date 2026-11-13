import sys

data = list(map(int, sys.stdin.read().split()))
n, m = data[0], data[1]
left = data[2:2 + n]
right = data[2 + n:2 + n + m]
# корабли r (вправо) и l (влево) встречаются, если r < l: в момент (l - r) / 2 в точке (l + r) / 2
used_left = [False] * n
shots = []
i = 0
for r in right:
    while i < n and left[i] <= r:
        i += 1
    if i < n:
        used_left[i] = True
        shots.append(((left[i] - r) // 2, (left[i] + r) // 2))
        i += 1
    else:
        shots.append((0, r))
for j in range(n):
    if not used_left[j]:
        shots.append((0, left[j]))
print("\n".join(f"{t} {x}" for t, x in shots))
