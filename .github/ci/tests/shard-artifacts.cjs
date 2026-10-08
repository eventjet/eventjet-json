const assert = require('node:assert/strict');
const {test} = require('node:test');
const shardArtifacts = require('../shard-artifacts.cjs');

function job(shard, attempt = 1, overrides = {}) {
  return {name: `Measure decoder performance (OPcache on, sample 1, shard ${shard})`,
    run_attempt: attempt, status: 'completed', conclusion: 'success', ...overrides};
}
function artifact(shard, attempt = 1, overrides = {}) {
  return {id: attempt * 10 + shard, name: `performance-on-sha-${attempt}-1-${shard}`, expired: false, ...overrides};
}
function select({jobs = [1, 2, 3, 4].map(shard => job(shard)),
  artifacts = [1, 2, 3, 4].map(shard => artifact(shard))} = {}) {
  const github = {
    rest: {actions: {listJobsForWorkflowRun: 'jobs', listWorkflowRunArtifacts: 'artifacts'}},
    paginate: async (method, parameters) => {
      assert.equal(parameters.run_id, 42);
      assert.equal(parameters.owner, 'eventjet');
      assert.equal(parameters.repo, 'json');
      if (method === 'jobs') {
        assert.equal(parameters.filter, 'all');
        return jobs;
      }
      assert.equal(method, 'artifacts');
      return artifacts;
    },
  };
  return shardArtifacts({github, context: {repo: {owner: 'eventjet', repo: 'json'}, runId: 42, sha: 'sha'},
    mode: 'on', sample: 1});
}

test('initial runs and aggregation-only reruns reuse the complete measurements', async () => {
  assert.deepEqual(await select(), [11, 12, 13, 14]);
});

test('a partial rerun combines the latest shard with untouched earlier shards', async () => {
  const jobs = [job(2, 2), ...[1, 2, 3, 4].map(shard => job(shard))];
  const artifacts = [...[1, 2, 3, 4].map(shard => artifact(shard)), artifact(2, 2)];
  assert.deepEqual(await select({jobs, artifacts}), [11, 22, 13, 14]);
});

test('a newer regression keeps its own artifact instead of the old passing result', async () => {
  const jobs = [...[1, 2, 3, 4].map(shard => job(shard)), job(2, 2, {conclusion: 'failure'})];
  const artifacts = [artifact(2, 2), ...[1, 2, 3, 4].map(shard => artifact(shard))];
  assert.deepEqual(await select({jobs, artifacts}), [11, 22, 13, 14]);
});

test('a newer failure without an artifact cannot fall back to earlier success', async () => {
  const jobs = [...[1, 2, 3, 4].map(shard => job(shard)), job(2, 2, {conclusion: 'failure'})];
  await assert.rejects(select({jobs}), /latest execution of shard 2/);
});

test('unfinished, canceled, and skipped latest executions cannot reuse older artifacts', async () => {
  for (const overrides of [{status: 'in_progress'}, {conclusion: 'cancelled'}, {conclusion: 'skipped'}]) {
    const jobs = [...[1, 2, 3, 4].map(shard => job(shard)), job(2, 2, overrides)];
    await assert.rejects(select({jobs}), /no completed measurement execution/);
  }
});

test('missing, expired, duplicate, other-mode and other-sample artifacts fail closed', async () => {
  const others = [1, 3, 4].map(shard => artifact(shard));
  for (const replacement of [[], [artifact(2, 1, {expired: true})], [artifact(2), artifact(2)],
    [artifact(2, 1, {name: 'performance-off-sha-1-1-2'})],
    [artifact(2, 1, {name: 'performance-on-sha-1-2-2'})]]) {
    await assert.rejects(select({artifacts: [...others, ...replacement]}), /latest execution of shard 2/);
  }
});

test('each shard selects its own latest execution regardless of API ordering', async () => {
  const jobs = [job(4, 3), job(2, 2), ...[1, 2, 3, 4].map(shard => job(shard)), job(4, 2)];
  const artifacts = [artifact(4, 2), artifact(4, 3), artifact(2, 2), ...[1, 2, 3, 4].map(shard => artifact(shard))];
  assert.deepEqual(await select({jobs, artifacts}), [11, 22, 13, 34]);
});

test('workflow selection passes the matrix values and downloads the selected artifact IDs', async () => {
  const fs = require('node:fs');
  const vm = require('node:vm');
  const workflow = fs.readFileSync(`${__dirname}/../../workflows/performance.yml`, 'utf8');
  const script = workflow.split('      - name: Select latest shard executions\n')[1]
    .split('          script: |\n')[1].split('      - name: Download shard measurements\n')[0]
    .replace(/^            /gm, '');
  const outputs = {};
  await vm.runInNewContext(`(async () => {${script}})()`, {
    github: {}, context: {},
    process: {env: {OPCACHE_MODE: 'off', SAMPLE: '5'}},
    core: {setOutput: (name, value) => { outputs[name] = value; }},
    require: path => {
      assert.equal(path, './.github/ci/shard-artifacts.cjs');
      return async ({mode, sample}) => {
        assert.equal(mode, 'off');
        assert.equal(sample, '5');
        return [11, 22, 13, 14];
      };
    },
  });
  assert.deepEqual(outputs, {ids: '11,22,13,14'});
  const download = workflow.split('      - name: Download shard measurements\n')[1].split('      - name:')[0];
  assert.ok(download.includes('artifact-ids: ${{ steps.shards.outputs.ids }}'));
  assert.ok(!download.includes('pattern:'));
});


test('carried-forward jobs keep their original execution attempt', async () => {
  const original = [1, 2, 3, 4].map(shard => job(shard, 1, {started_at: '2026-10-08T14:34:29Z', completed_at: '2026-10-08T14:37:04Z'}));
  const copied = original.map(entry => ({...entry, run_attempt: 2}));
  copied[1] = job(2, 2, {started_at: '2026-10-08T14:46:30Z'});
  const artifacts = [...[1, 2, 3, 4].map(shard => artifact(shard)), artifact(2, 2)];
  assert.deepEqual(await select({jobs: [...copied, ...original], artifacts}), [11, 22, 13, 14]);
});
