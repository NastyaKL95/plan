n, c = map(int, input().split())
a = sorted(map(int, input().split()))
i, j, boats = 0, n - 1, 0
while i <= j:
    if i < j and a[i] + a[j] <= c:
        i += 1
    j -= 1
    boats += 1
print(boats)
