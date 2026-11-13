n = int(input())
dp = [0] * (n + 1)
dp[0] = 1
for i in range(1, n + 1):
    dp[i] = sum(dp[i - s] for s in (1, 2, 3) if i - s >= 0)
print(dp[n])
