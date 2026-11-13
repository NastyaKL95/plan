MOD = 10**9 + 7
n = int(input())
m = int(input())
k = min(n, m)
s1 = k * (k + 1) // 2
s2 = k * (k + 1) * (2 * k + 1) // 6
s3 = s1 * s1
print(((n + 1) * (m + 1) * s1 - (n + m + 2) * s2 + s3) % MOD)
