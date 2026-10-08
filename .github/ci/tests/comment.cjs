const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {test} = require('node:test');

const workflow = fs.readFileSync(`${__dirname}/../../workflows/performance.yml`, 'utf8');
const script = workflow.split('      - name: Create or update comment\n')[1]
  .split('          script: |\n')[1].replace(/^            /gm, '');
const marker = '<!-- eventjet-performance -->';

async function publish({comments = [], head = 'head', base = 'base', state = 'open', available = true,
  result = 'success', size = 100} = {}) {
  const writes = [];
  const context = {
    repo: {owner: 'eventjet', repo: 'eventjet-json'}, serverUrl: 'https://github.com', runId: 42,
    payload: {pull_request: {number: 7, head: {sha: 'head'}, base: {sha: 'base'}}},
  };
  const github = {
    rest: {
      pulls: {get: async () => ({data: {state, head: {sha: head}, base: {sha: base}}})},
      issues: {
        listComments: 'list',
        createComment: async data => writes.push({method: 'create', ...data}),
        updateComment: async data => writes.push({method: 'update', ...data}),
      },
    },
    paginate: async (method, params) => {
      assert.equal(method, 'list');
      assert.equal(params.issue_number, 7);
      return comments;
    },
  };
  await vm.runInNewContext(`(async () => {${script}})()`, {
    github, context, core: {info() {}},
    process: {env: {SUMMARY_AVAILABLE: String(available), COMPARISON_RESULT: result, GITHUB_RUN_ATTEMPT: '2'}},
    require: name => {
      assert.equal(name, 'node:fs');
      return {statSync: () => ({size}), readFileSync: () => '## Performance fixture'};
    },
  });
  return writes;
}

test('creates one comment with revision and run link', async () => {
  const [write] = await publish();
  assert.equal(write.method, 'create');
  assert.equal(write.issue_number, 7);
  assert.ok(write.body.startsWith(marker));
  assert.match(write.body, /Commit: `head`/);
  assert.match(write.body, /actions\/runs\/42\/attempts\/2/);
});

test('updates the existing bot comment, leaving user comments alone', async () => {
  const writes = await publish({comments: [
    {id: 1, user: {login: 'someone'}, body: marker},
    {id: 2, user: {login: 'github-actions[bot]'}, body: 'Another report'},
    {id: 3, user: {login: 'github-actions[bot]'}, body: `${marker}\nOld report`},
  ]});
  assert.equal(writes.length, 1);
  assert.equal(writes[0].method, 'update');
  assert.equal(writes[0].comment_id, 3);
});

test('does not publish results after the head, target, or PR state changes', async () => {
  for (const options of [{head: 'new'}, {base: 'new'}, {state: 'closed'}]) {
    assert.deepEqual(await publish(options), []);
  }
});

test('replaces an old result with an unavailable notice when no summary exists', async () => {
  const [write] = await publish({available: false, result: 'failure', comments: [
    {id: 3, user: {login: 'github-actions[bot]'}, body: marker},
  ]});
  assert.equal(write.method, 'update');
  assert.match(write.body, /comparison unavailable/);
});

test('keeps regression results visible even when the gate fails', async () => {
  const [write] = await publish({result: 'failure'});
  assert.match(write.body, /Performance fixture/);
  assert.match(write.body, /performance job failed/);
});

test('rejects oversized artifacts before writing', async () => {
  await assert.rejects(publish({size: 50001}), /size limit/);
});
