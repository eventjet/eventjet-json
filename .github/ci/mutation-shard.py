"""Distribute every PHP source file once, balancing by code lines."""

import pathlib
import sys


def weight(path):
    return sum(
        1
        for line in path.read_text().splitlines()
        if line.strip() and not line.lstrip().startswith(("/", "*"))
    )


def partition(files, count):
    shards = [[] for _ in range(count)]
    sizes = [0] * count
    for path in sorted(files, key=lambda path: (-weight(path), str(path))):
        index = min(range(count), key=lambda index: sizes[index])
        shards[index].append(path)
        sizes[index] += weight(path)
    return shards


if __name__ == "__main__":
    shard, count = map(int, sys.argv[1:])
    if not 1 <= shard <= count:
        sys.exit("Expected 1 <= shard <= shard count")
    files = list(pathlib.Path("src").rglob("*.php"))
    if not files:
        sys.exit("No PHP source files found")
    for path in partition(files, count)[shard - 1]:
        print(path)
