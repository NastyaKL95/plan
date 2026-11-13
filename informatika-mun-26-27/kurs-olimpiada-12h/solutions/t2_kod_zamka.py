N = int(input())
if N == 0:
    print(10)
elif N == 1:
    print(1)
else:
    digits = []
    for d in range(9, 1, -1):
        while N % d == 0:
            N //= d
            digits.append(d)
    if N != 1:
        print(-1)
    else:
        print(int("".join(map(str, sorted(digits)))))
