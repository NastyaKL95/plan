import sys

def main():
    data = sys.stdin.buffer.read().split()
    n, m, k = int(data[0]), int(data[1]), int(data[2])
    g = [[] for _ in range(n + 1)]
    pos = 3
    for _ in range(m):
        u, v = int(data[pos]), int(data[pos + 1])
        pos += 2
        g[u].append(v)
        g[v].append(u)
    best = [-1] * (n + 1)
    buckets = [[] for _ in range(n + 1)]
    for _ in range(k):
        p, h = int(data[pos]), int(data[pos + 1])
        pos += 2
        h = min(h, n)
        if h > best[p]:
            best[p] = h
            buckets[h].append(p)
    for h in range(n, -1, -1):
        for v in buckets[h]:
            if best[v] != h or h == 0:
                continue
            for u in g[v]:
                if best[u] < h - 1:
                    best[u] = h - 1
                    buckets[h - 1].append(u)
    res = [i for i in range(1, n + 1) if best[i] >= 0]
    print(len(res))
    print(*res)

main()
