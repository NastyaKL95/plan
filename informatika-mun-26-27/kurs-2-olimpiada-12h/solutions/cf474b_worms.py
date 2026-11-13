import sys
from bisect import bisect_left
from itertools import accumulate

data = sys.stdin.read().split()
n = int(data[0])
pref = list(accumulate(map(int, data[1:n + 1])))
m = int(data[n + 1])
out = [bisect_left(pref, int(q)) + 1 for q in data[n + 2:n + 2 + m]]
print("\n".join(map(str, out)))
