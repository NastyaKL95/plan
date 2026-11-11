N = int(input())
if N == 0:
    print(10)
elif N == 1:
    print(1)
else:
    n = N
    digits = []
    for d in range(9, 1, -1):
        while n % d == 0:
            n //= d
            digits.append(d)
    if n != 1:
        print(-1)
    else:
        digits.sort()
        print(int("".join(map(str, digits))))
