n = int(input())
c = list(map(int, input().split()))
dp = [0] * n
dp[0] = c[0]
for i in range(1, n):
    dp[i] = dp[i - 1] + c[i]
    if i >= 2:
        dp[i] = min(dp[i], dp[i - 2] + c[i])
print(dp[n - 1])
