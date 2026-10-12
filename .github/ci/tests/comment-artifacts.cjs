const assert = require('node:assert/strict');
const {test} = require('node:test');
const commentArtifacts = require('../comment-artifacts.cjs');

function job(mode, attempt = 1, overrides = {}) {
  return {name: `Compare decoder performance (OPcache ${mode})`,
    started_at: `2026-10-08T12:0${attempt}:00Z`, completed_at: `2026-10-08T12:0${attempt}:30Z`,
    run_attempt: attempt, status: 'completed', conclusion: 'success', ...overrides};
}
function artifact(mode, attempt = 1, overrides = {}) {
  return {id: attempt * 10 + (mode === 'on' ? 1 : 2), name: `performance-comment-${attempt}-${mode}`, expired: false,
    ...overrides};
}
function select({jobs = [job('on'), job('off')], artifacts = [artifact('on'), artifact('off')]} = {}) {
  const github = {
    rest: {actions: {listJobsForWorkflowRun: 'jobs', listWorkflowRunArtifacts: 'artifacts'}},
    paginate: async (method, parameters) => {
      assert.equal(parameters.run_id, 42);
      if (method === 'jobs') {
        assert.equal(parameters.filter, 'all');
        return jobs;
      }
      assert.equal(method, 'artifacts');
      return artifacts;
    },
  };
  return commentArtifacts({github, context: {repo: {owner: 'eventjet', repo: 'json'}, runId: 42}});
}

test('selects the summary of each mode', async () => {
  assert.deepEqual(await select(), {on: '11', off: '12'});
});

test('a rerun of one mode keeps the summary of the carried-over mode', async () => {
  const original = [job('on'), job('off', 1, {conclusion: 'failure'})];
  const jobs = [...original, {...original[0], run_attempt: 2}, job('off', 2)];
  const artifacts = [artifact('on'), artifact('off'), artifact('off', 2)];
  assert.deepEqual(await select({jobs, artifacts}), {on: '11', off: '22'});
});

test('a newer execution without a summary does not fall back to an older one', async () => {
  const jobs = [job('on'), job('off'), job('off', 2, {conclusion: 'failure'})];
  assert.deepEqual(await select({jobs}), {on: '11', off: ''});
});

test('missing executions and missing or expired summaries are unavailable', async () => {
  assert.deepEqual(await select({jobs: [job('on')]}), {on: '11', off: ''});
  assert.deepEqual(await select({jobs: [job('on'), job('off', 1, {conclusion: 'skipped'})]}), {on: '11', off: ''});
  assert.deepEqual(await select({artifacts: [artifact('on', 1, {expired: true})]}), {on: '', off: ''});
});
