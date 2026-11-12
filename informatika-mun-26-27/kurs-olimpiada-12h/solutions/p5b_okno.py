n, S = map(int, input().split())
a = list(map(int, input().split()))
left = 0
cur = 0
best = 0
for right in range(n):
    cur += a[right]
    while cur > S and left <= right:
        cur -= a[left]
        left += 1
    best = max(best, right - left + 1)
print(best)
