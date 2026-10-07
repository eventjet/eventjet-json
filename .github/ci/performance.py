"""Compare identical workloads on one machine; retain samples for gate calibration."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import shlex
import shutil
import statistics
import subprocess
import xml.etree.ElementTree as ET


def git(*args):
    return subprocess.check_output(['git', *args])


def export(ref, destination, paths):
    names = git('ls-tree', '-rz', '--name-only', ref, '--', *paths).split(b'\0')
    # git archive honors export-ignore, which would omit benchmark fixtures.
    for name in filter(None, names):
        path = destination / os.fsdecode(name)
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(git('show', f'{ref}:{os.fsdecode(name)}'))


def samples(path):
    root = ET.parse(path).getroot()
    if root.findall('.//error') or root.findall('.//failure'):
        raise ValueError(f'Benchmark failed: {path}')
    result = {}
    for benchmark in root.iter('benchmark'):
        for subject in benchmark.findall('subject'):
            for variant in subject.findall('variant'):
                parameters = variant.find('parameter-set')
                key = f"{benchmark.get('class')} / {subject.get('name')} / {parameters.get('name')}"
                iterations = variant.findall('iteration')
                times = [float(item.attrib['time-net']) / int(variant.attrib['revs']) for item in iterations]
                memory = [int(item.attrib['mem-peak']) for item in iterations]
                if not times or min(times) <= 0 or key in result:
                    raise ValueError(f'Invalid or duplicate benchmark samples: {key}')
                result[key] = {'time_us': times, 'peak_bytes': max(memory)}
    if not result:
        raise ValueError(f'No benchmark samples: {path}')
    return result


def summarize(pairs):
    keys = set(pairs[0][0])
    if any(set(side) != keys for pair in pairs for side in pair):
        raise ValueError('Benchmark cases differ between runs')
    result = {}
    for key in sorted(keys):
        baseline = [x for a, _ in pairs for x in a[key]['time_us']]
        candidate = [x for _, b in pairs for x in b[key]['time_us']]
        changes = [100 * (statistics.median(b[key]['time_us']) / statistics.median(a[key]['time_us']) - 1) for a, b in pairs]
        result[key] = {
            'baseline_us': statistics.median(baseline),
            'candidate_us': statistics.median(candidate),
            'change_percent': statistics.median(changes),
            'paired_changes_percent': changes,
            'baseline_cv_percent': 100 * statistics.stdev(baseline) / statistics.mean(baseline),
            'candidate_cv_percent': 100 * statistics.stdev(candidate) / statistics.mean(candidate),
            'baseline_peak_bytes': max(a[key]['peak_bytes'] for a, _ in pairs),
            'candidate_peak_bytes': max(b[key]['peak_bytes'] for _, b in pairs),
        }
    return result


def report(metadata, comparison, calibration):
    lines = [
        '## Performance comparison', '',
        f"Baseline: `{metadata['baseline']}`. Candidate: `{metadata['candidate']}`.", '',
        'Reporting only: no regression threshold has been calibrated. Positive changes mean slower execution.', '',
        'Both versions use the baseline workloads and candidate dependency lock on the same runner. '
        'Three independent pairs alternate A/B and B/A order, with five iterations per invocation. '
        'Cold/warm settings come from the frozen benchmark suite; PCOV, Xdebug coverage, OPcache, and JIT are disabled.', '',
        'The unchanged-code A/A comparison estimates noise for this run, not a statistical confidence interval. '
        'Archive multiple runs before defining a required regression gate. Memory is whole benchmark-process peak, not decoder-only allocation.', '',
        '| Workload | Base µs/decode | Candidate µs/decode | Paired change | Pair range | A/A max absolute change | CV base / candidate | Peak MiB base / candidate |',
        '|---|---:|---:|---:|---:|---:|---:|---:|',
    ]
    for key, row in comparison.items():
        noise = max(abs(x) for x in calibration[key]['paired_changes_percent'])
        changes = row['paired_changes_percent']
        label = key.replace('|', '\\|')
        lines.append(f"| {label} | {row['baseline_us']:.2f} | {row['candidate_us']:.2f} | {row['change_percent']:+.1f}% | {min(changes):+.1f}% to {max(changes):+.1f}% | {noise:.1f}% | {row['baseline_cv_percent']:.1f}% / {row['candidate_cv_percent']:.1f}% | {row['baseline_peak_bytes']/2**20:.2f} / {row['candidate_peak_bytes']/2**20:.2f} |")
    if metadata['workloads_changed']:
        lines += ['', 'Benchmark or fixture files changed in this candidate. Those changes are excluded from this comparison; the candidate suite is also run separately and retained as `candidate-workloads.xml`.']
    return '\n'.join(lines) + '\n'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--base', required=True)
    parser.add_argument('--candidate', default='HEAD')
    parser.add_argument('--exec-prefix', default='', help='Optional service command prefix for local validation')
    args = parser.parse_args()
    base = git('rev-parse', '--verify', args.base + '^{commit}').decode().strip()
    candidate = git('rev-parse', '--verify', args.candidate + '^{commit}').decode().strip()
    output = Path('.perf')
    if output.exists():
        raise FileExistsError('Move or remove .perf before starting a new comparison')
    results = output / 'results'
    results.mkdir(parents=True)
    metadata = {'baseline': base, 'candidate': candidate, 'lock_sha256': hashlib.sha256(Path('composer.lock').read_bytes()).hexdigest(), 'workloads_changed': bool(git('diff', '--name-only', base, candidate, '--', 'benchmarks', 'tests', 'phpbench.json'))}
    metadata['cpu'] = next((line.split(':', 1)[1].strip() for line in Path('/proc/cpuinfo').read_text().splitlines() if line.startswith('model name')), 'unknown')
    (results / 'metadata.json').write_text(json.dumps(metadata, indent=2) + '\n')
    if not git('ls-tree', '--name-only', base, '--', 'benchmarks').strip():
        text = f"## Performance comparison\n\nNo comparable baseline: `{base}` has no benchmark suite. Candidate: `{candidate}`. No regression verdict is available.\n"
        (results / 'summary.md').write_text(text)
        if os.getenv('GITHUB_STEP_SUMMARY'):
            with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as summary:
                summary.write(text)
        return
    export(base, output / 'baseline', ['src', 'benchmarks', 'tests'])
    export(candidate, output / 'candidate', ['src', 'benchmarks', 'tests'])
    workspace = output / 'workspace'
    workspace.mkdir()
    shutil.copytree('vendor', workspace / 'vendor', symlinks=True)
    for name in ['composer.json', 'composer.lock']:
        shutil.copy2(name, workspace / name)
    config = json.loads(Path('phpbench.json').read_text())
    config['runner.env_enabled_providers'] = ['php', 'uname', 'opcache', 'unix_sysload']
    config['runner.php_config'] = {'pcov.enabled': '0', 'opcache.enable_cli': '0', 'opcache.jit': '0', 'xdebug.mode': 'off', 'memory_limit': '1G'}
    (workspace / 'phpbench.json').write_text(json.dumps(config, indent=2))
    prefix = shlex.split(args.exec_prefix)

    def execute(command):
        subprocess.run([*prefix, *command], cwd=workspace, check=True, timeout=90)

    for name in ['benchmarks', 'tests']:
        shutil.copytree(output / 'baseline' / name, workspace / name)

    def measure(version, name):
        shutil.rmtree(workspace / 'src', ignore_errors=True)
        shutil.copytree(output / version / 'src', workspace / 'src')
        execute(['composer', 'dump-autoload', '--optimize', '--no-interaction'])
        execute(['vendor/bin/phpbench', 'run', '--iterations=5', '--progress=none', '--dump-file=../results/' + name + '.xml'])
        return samples(results / (name + '.xml'))

    experiments = {}
    for experiment in ['calibration', 'comparison']:
        pairs = []
        for index in range(3):
            pair = {}
            for side in (['a', 'b'] if index % 2 == 0 else ['b', 'a']):
                version = 'candidate' if experiment == 'comparison' and side == 'b' else 'baseline'
                pair[side] = measure(version, f'{experiment}-{index}-{side}')
            pairs.append((pair['a'], pair['b']))
        experiments[experiment] = summarize(pairs)
    if metadata['workloads_changed']:
        for name in ['benchmarks', 'tests']:
            shutil.rmtree(workspace / name)
            shutil.copytree(output / 'candidate' / name, workspace / name)
        measure('candidate', 'candidate-workloads')
    (results / 'results.json').write_text(json.dumps({'metadata': metadata, **experiments}, indent=2) + '\n')
    text = report(metadata, experiments['comparison'], experiments['calibration'])
    (results / 'summary.md').write_text(text)
    if os.getenv('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as summary:
            summary.write(text)
    print(text)


if __name__ == '__main__':
    main()
