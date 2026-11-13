n = int(input())
t = sorted(map(int, input().split()))
now = total = 0
for x in t:
    now += x
    total += now
print(total)
