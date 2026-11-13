s, t = input().split()
t = int(t)
n = len(s)
cnt = 0
for mask in range(1 << (n - 1)):
    total, cur = 0, s[0]
    for i in range(1, n):
        if mask >> (i - 1) & 1:
            total += int(cur)
            cur = s[i]
        else:
            cur += s[i]
    if total + int(cur) == t:
        cnt += 1
print(cnt)
