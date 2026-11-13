n, t = map(int, input().split())
a = list(map(int, input().split()))
best = s = left = 0
for right in range(n):
    s += a[right]
    while s > t:
        s -= a[left]
        left += 1
    best = max(best, right - left + 1)
print(best)
