S = int(input())
if S < 10:
    print(S)
else:
    digits = []
    while S > 9:
        digits.append(9)
        S -= 9
    digits.append(S)
    digits.reverse()
    print(int("".join(map(str, digits))))
