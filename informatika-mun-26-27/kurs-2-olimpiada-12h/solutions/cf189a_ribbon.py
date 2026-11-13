n, a, b, c = map(int, input().split())
NEG = -1
dp = [NEG] * (n + 1)
dp[0] = 0
for i in range(1, n + 1):
    for p in (a, b, c):
        if i >= p and dp[i - p] != NEG:
            dp[i] = max(dp[i], dp[i - p] + 1)
print(dp[n])
