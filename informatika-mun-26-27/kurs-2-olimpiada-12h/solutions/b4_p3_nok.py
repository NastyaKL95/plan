from math import gcd

n = int(input())
a = list(map(int, input().split()))
g, l = 0, 1
for x in a:
    g = gcd(g, x)
    l = l * x // gcd(l, x)
print(g, l)
