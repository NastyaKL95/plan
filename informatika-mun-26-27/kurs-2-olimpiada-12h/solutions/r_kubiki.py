import sys

data = sys.stdin.read().split()
n = int(data[0])
out = []
for i in range(n):
    a = sorted(map(int, data[1 + 6 * i: 7 + 6 * i]))
    out.append("yes" if a[0] + a[5] == a[1] + a[4] == a[2] + a[3] else "no")
print("\n".join(out))
