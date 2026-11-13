from collections import Counter

n = int(input())
a = list(map(int, input().split()))
ans = 0
for k in range(n):
    seen = Counter()
    for j in range(k):
        ans += seen[-a[j] - a[k]]
        seen[a[j]] += 1
print(ans)
