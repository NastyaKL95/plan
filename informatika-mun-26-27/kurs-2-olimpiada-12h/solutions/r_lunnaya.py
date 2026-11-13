def lsum(x, y):
    n = max(len(x), len(y))
    x, y = x.rjust(n, "0"), y.rjust(n, "0")
    return str(int("".join(max(p, q) for p, q in zip(x, y))))


def lmul(x, y):
    res = "0"
    for i, d in enumerate(reversed(y)):
        row = "".join(min(c, d) for c in x) + "0" * i
        res = lsum(res, row)
    return res


a = input().strip()
b = input().strip()
print(lsum(a, b), lmul(a, b))
