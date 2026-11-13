from collections import defaultdict

n, k = map(int, input().split())
a = list(map(int, input().split()))
cnt = defaultdict(int)
cnt[0] = 1
s = 0
ans = 0
for x in a:
    s = (s + x) % k
    ans += cnt[s]
    cnt[s] += 1
print(ans)
