n, a, b = map(int, input().split())
if n <= 2 * a:
    print(1)
else:
    print(1 + (n - 2 * a + b - 1) // b)
