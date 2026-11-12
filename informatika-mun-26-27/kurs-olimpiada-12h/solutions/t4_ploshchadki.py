n = int(input())
m = int(input())
ans = 0
for s in range(1, min(n, m) + 1):
    ans += (n - s + 1) * (m - s + 1)
print(ans)
