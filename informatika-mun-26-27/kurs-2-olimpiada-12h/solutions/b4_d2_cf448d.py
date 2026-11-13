n, m, k = map(int, input().split())
lo, hi = 1, n * m
while lo < hi:
    mid = (lo + hi) // 2
    if sum(min(m, mid // i) for i in range(1, n + 1)) >= k:
        hi = mid
    else:
        lo = mid + 1
print(lo)
