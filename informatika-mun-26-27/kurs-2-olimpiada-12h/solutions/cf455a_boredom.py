n = int(input())
a = list(map(int, input().split()))
M = max(a)
cnt = [0] * (M + 1)
for x in a:
    cnt[x] += 1
take, skip = 0, 0
for v in range(1, M + 1):
    take, skip = skip + v * cnt[v], max(take, skip)
print(max(take, skip))
