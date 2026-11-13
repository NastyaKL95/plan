s = input().strip()
cnt = [0] * 26
for c in s:
    cnt[ord(c) - 97] += 1
odd = [i for i in range(26) if cnt[i] % 2]
if len(odd) > 1:
    print("NO")
else:
    half = "".join(chr(97 + i) * (cnt[i] // 2) for i in range(26))
    mid = chr(97 + odd[0]) if odd else ""
    print(half + mid + half[::-1])
