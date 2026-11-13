def min_digits(x):
    cnt = 0
    for d in range(9, 1, -1):
        while x % d == 0:
            x //= d
            cnt += 1
    return cnt if x == 1 else None


n, k = map(int, input().split())
need = min_digits(n)
if need is None or need > k:
    print(-1)
else:
    res = []
    for pos in range(k):
        left = k - pos - 1
        for d in range(9, 0, -1):
            if n % d == 0:
                c = min_digits(n // d)
                if c is not None and c <= left:
                    res.append(str(d))
                    n //= d
                    break
    print("".join(res))
