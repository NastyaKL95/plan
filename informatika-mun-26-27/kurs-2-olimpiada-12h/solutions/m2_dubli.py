st = []
for c in input().strip():
    if st and st[-1] == c:
        st.pop()
    else:
        st.append(c)
print("".join(st) if st else "-")
