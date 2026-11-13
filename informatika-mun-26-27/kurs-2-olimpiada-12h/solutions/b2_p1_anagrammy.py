a, b = input().split()
cnt = [0] * 26
for c in a:
    cnt[ord(c) - ord("a")] += 1
for c in b:
    cnt[ord(c) - ord("a")] -= 1
print("YES" if all(x == 0 for x in cnt) else "NO")
