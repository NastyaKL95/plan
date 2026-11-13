n, m = map(int, input().split())
a = [list(map(int, input().split())) for _ in range(n)]
dp = [[0] * m for _ in range(n)]
for i in range(n):
    for j in range(m):
        if i == 0 and j == 0:
            dp[i][j] = a[0][0]
        elif i == 0:
            dp[i][j] = dp[i][j - 1] + a[i][j]
        elif j == 0:
            dp[i][j] = dp[i - 1][j] + a[i][j]
        else:
            dp[i][j] = max(dp[i - 1][j], dp[i][j - 1]) + a[i][j]
print(dp[n - 1][m - 1])
