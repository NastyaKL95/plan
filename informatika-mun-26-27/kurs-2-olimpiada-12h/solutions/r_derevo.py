from math import gcd

a, b = map(int, input().split())
g = gcd(a, b)
a, b = a // g, b // g
path = ["R"]
while a != b:
    if a < b:
        q = (b - 1) // a
        path.append("L" * q)
        b -= q * a
    else:
        q = (a - 1) // b
        path.append("R" * q)
        a -= q * b
print("".join(path))
