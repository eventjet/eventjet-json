# Complete constructor plan reuse

The prototype reduced warm end-to-end decode time for **enum union** against
`d56aa024ce0db5411addb61298b4da4be021d39b`. These measurements motivated this
implementation; the pull request's performance workflow measures the final code.

| Run | Baseline microseconds | Prototype microseconds | Time reduction |
| --- | --- | --- | --- |
| confirmation | 2.25 | 1.86 | 17.5% |
| affinity | 3.17 | 2.55 | 19.6% |

Measured on Windows PHP 8.4.1 NTS with OPcache enabled, its default optimizer,
JIT and assertions disabled, and file-update protection zero. Fixtures were
prepared before timing. Ten untimed decodes warmed each process; timed loops
included JSON parsing, hydration, and replacing the preceding result. Hydration
and JSON round-trip checks ran outside timing. Every sample used a fresh PHP
process; baseline/candidate order alternated. Reported reductions compare medians.

Confirmation used eleven pairs; affinity used fifteen pairs pinned to CPU 0.
The enum-union workload used 30000/20000 decodes per sample respectively;
the 1000-record batch used 600/400. Unpinned samples showed substantial scheduling
noise. These are targeted local measurements, not hosted-CI calibration or a
claim of a 10% gain across realistic documents. Independent optimization gains
must not be added together.

[Raw samples](constructor-plan-reuse-samples.json) include the confirmation, affinity, and
fresh-process first-decode results, including regressions. Cold CLI measurements
include class loading and compilation and are not fresh PHP-FPM measurements.
Both OPcache modes and fresh PHP-FPM requests need the existing performance
workflow before merging; a warm diagnostic gain does not establish a passing
regression gate. See [performance methodology](../performance.md).
