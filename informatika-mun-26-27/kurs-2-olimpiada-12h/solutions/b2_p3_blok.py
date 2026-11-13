s = input().strip()
best_len, best_ch = 0, ""
i = 0
while i < len(s):
    j = i
    while j < len(s) and s[j] == s[i]:
        j += 1
    if j - i > best_len:
        best_len, best_ch = j - i, s[i]
    i = j
print(best_ch, best_len)
