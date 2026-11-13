c = float(input())
lo, hi = 0.0, max(1.0, c)
for _ in range(100):
    mid = (lo + hi) / 2
    if mid ** 3 + mid < c:
        lo = mid
    else:
        hi = mid
print(f"{lo:.6f}")
