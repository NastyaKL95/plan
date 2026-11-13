n = int(input())
a = list(map(int, input().split()))
b = list(map(int, input().split()))
c = sorted(x - y for x, y in zip(a, b))
i, j, ans = 0, n - 1, 0
while i < j:
    if c[i] + c[j] > 0:
        ans += j - i
        j -= 1
    else:
        i += 1
print(ans)
