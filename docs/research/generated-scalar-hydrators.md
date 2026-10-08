# Generated scalar hydrators

The implementation emits scalar checks and a positional constructor call from
reflected PHP declarations, then stores that closure in the existing plan.
Only constructor-only scalar plans invoke it. Missing or invalid fields return
to the original decoder for defaults and detailed errors. Anonymous classes
retain the original path. JSON strings and values never enter generated source;
property names are quoted and class names come from Reflection's canonical name.

This draft uses runtime `eval` with a narrowly scoped lint exception. It avoids
filesystem writes. Compilation is deferred until an eligible constructor plan
has been reused 128 times, so short requests do not load the compiler. Plans for classes
with additional public properties never access the compiler. The resulting
closure (including an unsupported result) is cached. Build-time
generation is an alternative deployment design, not part of these measurements.

The prototype reduced warm end-to-end decode time for **record batch 1000** against
`d56aa024ce0db5411addb61298b4da4be021d39b`. These measurements motivated this
implementation; the pull request's performance workflow measures the final code.

| Run | Baseline microseconds | Prototype microseconds | Time reduction |
| --- | --- | --- | --- |
| confirmation | 3931.50 | 3255.20 | 17.2% |
| affinity | 4801.21 | 3280.11 | 31.7% |

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

[Raw samples](generated-scalar-hydrators-samples.json) include the confirmation, affinity, and
fresh-process first-decode results, including regressions. Cold CLI measurements
include class loading and compilation and are not fresh PHP-FPM measurements.
Both OPcache modes and fresh PHP-FPM requests need the existing performance
workflow before merging; a warm diagnostic gain does not establish a passing
regression gate. See [performance methodology](../performance.md).

The initial implementation eagerly compiled while constructing metadata, which
regressed cold scalar decoding by 8.49% with OPcache and 15.44% without it in CI.
An interleaved 21-pair local PHP 8.4.26 comparison reproduced a 12.71% regression
without OPcache. Deferring compilation changed that comparison to -2.33%; a
separate warm comparison retained a 12.58% reduction with OPcache. These local
diagnostics do not replace the full CI gate. Compiling on the second use still
charged short requests before they could amortize compilation. The final
activation threshold is 128 cached-plan uses; in particular, a fresh 100-record
batch never compiles. A process-isolated autoload test locks down this boundary
and reuse of the same closure afterward. Default and exception tests explicitly
warm past the boundary before exercising the generated path.

Hosted timing also contains false positives: an unchanged `d56aa02` versus
`d56aa02` [control run](https://github.com/eventjet/eventjet-json/actions/runs/37815574072)
reported an 8.36% cold JSON:API regression. No thresholds or performance tooling
were changed to accommodate that noise.
