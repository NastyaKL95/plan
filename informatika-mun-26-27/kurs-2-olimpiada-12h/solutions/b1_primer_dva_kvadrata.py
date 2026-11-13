from math import isqrt

n = int(input())
cnt = 0
a = 1
while 2 * a * a <= n:
    rest = n - a * a
    b = isqrt(rest)
    if b * b == rest:
        cnt += 1
    a += 1
print(cnt)
