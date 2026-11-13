n, w = map(int, input().split())
a = list(map(int, input().split()))
cnt = 0
for mask in range(1 << n):
    s = 0
    for i in range(n):
        if mask >> i & 1:
            s += a[i]
    if s == w:
        cnt += 1
print(cnt)
