MOD = 10**9 + 7
n, m = map(int, input().split())
g = [input() for _ in range(n)]
dp = [[0] * m for _ in range(n)]
for i in range(n):
    for j in range(m):
        if g[i][j] == "#":
            continue
        if i == 0 and j == 0:
            dp[i][j] = 1
            continue
        up = dp[i - 1][j] if i else 0
        left = dp[i][j - 1] if j else 0
        dp[i][j] = (up + left) % MOD
print(dp[n - 1][m - 1])
