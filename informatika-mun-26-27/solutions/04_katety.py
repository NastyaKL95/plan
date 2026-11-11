n = int(input())
m = int(input())
ans = 0
for k in (1, 2, 3):
    ans += 4 * max(0, n - k + 1) * max(0, m - k + 1)
    ans += 2 * max(0, n - 2 * k + 1) * max(0, m - k + 1)
    ans += 2 * max(0, n - k + 1) * max(0, m - 2 * k + 1)
print(ans)
