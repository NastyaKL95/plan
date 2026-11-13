n, x, y = map(int, input().split())
first = min(x, y)
lo, hi = 0, (n - 1) * max(x, y)
while lo < hi:
    mid = (lo + hi) // 2
    if mid // x + mid // y >= n - 1:
        hi = mid
    else:
        lo = mid + 1
print(first + lo)
