"""Exercise report calculations and reject incomplete benchmark output."""

import importlib.util
from pathlib import Path
import tempfile
import sys

sys.dont_write_bytecode = True

spec = importlib.util.spec_from_file_location('performance', Path(__file__).with_name('performance.py'))
performance = importlib.util.module_from_spec(spec)
spec.loader.exec_module(performance)

with tempfile.TemporaryDirectory() as directory:
    path = Path(directory) / 'samples.xml'
    path.write_text('''<phpbench><suite><benchmark class="Decoder"><subject name="warm">
      <variant revs="10"><parameter-set name="objects"/>
        <iteration time-net="100" mem-peak="1024"/>
        <iteration time-net="120" mem-peak="2048"/>
      </variant></subject></benchmark></suite></phpbench>''')
    baseline = performance.samples(path)
    key = 'Decoder / warm / objects'
    assert baseline[key] == {'time_us': [10, 12], 'peak_bytes': 2048}
    candidate = {key: {'time_us': [12.5, 15], 'peak_bytes': 4096}}
    result = performance.summarize([(baseline, candidate)] * 3)[key]
    assert result['change_percent'] == 25
    assert result['paired_changes_percent'] == [25, 25, 25]
    assert result['candidate_peak_bytes'] == 4096
    assert performance.summarize([(baseline, baseline)] * 3)[key]['change_percent'] == 0
    try:
        performance.summarize([(baseline, {})])
    except ValueError:
        pass
    else:
        raise AssertionError('Missing cases must fail the comparison')
    for invalid in ['<phpbench/>', '<phpbench><error/></phpbench>']:
        path.write_text(invalid)
        try:
            performance.samples(path)
        except ValueError:
            pass
        else:
            raise AssertionError('Empty or failed benchmarks must fail the comparison')
print('Performance calculations and invalid-output checks passed.')
