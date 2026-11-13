n, k = map(int, input().split())
if n <= k:
    print(0)
else:
    print((n + k - 1) // k - 1)
