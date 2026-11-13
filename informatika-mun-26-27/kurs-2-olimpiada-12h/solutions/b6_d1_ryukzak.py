n, w = map(int, input().split())
dp = [0] * (w + 1)
for _ in range(n):
    wi, ci = map(int, input().split())
    for cap in range(w, wi - 1, -1):
        dp[cap] = max(dp[cap], dp[cap - wi] + ci)
print(dp[w])
