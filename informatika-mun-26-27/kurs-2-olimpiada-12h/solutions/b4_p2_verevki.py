n, k = map(int, input().split())
a = list(map(int, input().split()))
lo, hi = 0, max(a)
while lo < hi:
    mid = (lo + hi + 1) // 2
    if sum(x // mid for x in a) >= k:
        lo = mid
    else:
        hi = mid - 1
print(lo)
