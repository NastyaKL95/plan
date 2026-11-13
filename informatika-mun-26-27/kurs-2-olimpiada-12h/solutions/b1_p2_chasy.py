def to_min(t):
    h, m = map(int, t.split(":"))
    return h * 60 + m


a, b = input().split()
cnt = 0
for t in range(to_min(a), to_min(b) + 1):
    s = f"{t // 60:02d}{t % 60:02d}"
    if s == s[::-1]:
        cnt += 1
print(cnt)
