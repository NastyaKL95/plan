n, k = map(int, input().split())
a = sorted(map(int, input().split()))[n // 2:]


def cost(x):
    return sum(x - v for v in a if v < x)


lo, hi = a[0], a[0] + k
while lo < hi:
    mid = (lo + hi + 1) // 2
    if cost(mid) <= k:
        lo = mid
    else:
        hi = mid - 1
print(lo)
