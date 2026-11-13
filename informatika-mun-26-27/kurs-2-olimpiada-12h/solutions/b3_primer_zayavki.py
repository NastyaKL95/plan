n = int(input())
segs = [tuple(map(int, input().split())) for _ in range(n)]
segs.sort(key=lambda x: x[1])
cnt, last_end = 0, -10**18
for l, r in segs:
    if l >= last_end:
        cnt += 1
        last_end = r
print(cnt)
