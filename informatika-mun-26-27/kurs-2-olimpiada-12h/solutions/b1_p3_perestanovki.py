from itertools import permutations

n = int(input())
cnt = 0
for p in permutations(range(1, n + 1)):
    if all(abs(p[i] - p[i + 1]) != 1 for i in range(n - 1)):
        cnt += 1
print(cnt)
