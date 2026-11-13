a, b = input().split()
n = max(len(a), len(b))
a, b = a.rjust(n, "0"), b.rjust(n, "0")
res = []
carry = 0
for i in range(n - 1, -1, -1):
    d = int(a[i]) + int(b[i]) + carry
    res.append(str(d % 10))
    carry = d // 10
if carry:
    res.append(str(carry))
print("".join(reversed(res)))
