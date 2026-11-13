n, s = map(int, input().split())
a = sorted(map(int, input().split()))
i, j, cnt = 0, n - 1, 0
while i < j:
    if a[i] + a[j] <= s:
        cnt += j - i
        i += 1
    else:
        j -= 1
print(cnt)
