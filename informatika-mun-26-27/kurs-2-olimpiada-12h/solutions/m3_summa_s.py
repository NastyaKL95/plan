n, s = map(int, input().split())
a = list(map(int, input().split()))
left = cur = cnt = 0
for right in range(n):
    cur += a[right]
    while cur > s:
        cur -= a[left]
        left += 1
    if cur == s:
        cnt += 1
print(cnt)
