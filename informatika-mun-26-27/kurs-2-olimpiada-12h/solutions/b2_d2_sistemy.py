DIG = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"
s, p, q = input().split()
p, q = int(p), int(q)
x = 0
for c in s.upper():
    x = x * p + DIG.index(c)
if x == 0:
    print(0)
else:
    out = []
    while x:
        out.append(DIG[x % q])
        x //= q
    print("".join(reversed(out)))
