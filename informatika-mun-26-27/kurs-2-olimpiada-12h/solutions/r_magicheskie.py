s = input().strip()
vowels = set("aeiouy")
ans = 0
i = 0
while i < len(s):
    j = i
    while j < len(s) and (s[j] in vowels) == (s[i] in vowels):
        j += 1
    ans += (j - i - 1) // 2
    i = j
print(ans)
