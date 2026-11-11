from collections import defaultdict

n = int(input())
a = list(map(int, input().split()))
pieces = defaultdict(int)
for x in a:
    k = 0
    while x % 2 == 0:
        x //= 2
        k += 1
    pieces[x] += 1 << k

best_c, best_d = -1, 10**18
for d, c in pieces.items():
    if c > best_c or (c == best_c and d < best_d):
        best_c, best_d = c, d
print(best_c, best_d)
