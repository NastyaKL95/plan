from bisect import bisect_left, bisect_right

n = int(input())
a = sorted(map(int, input().split()))
q = int(input())
for _ in range(q):
    l, r = map(int, input().split())
    print(bisect_right(a, r) - bisect_left(a, l))
