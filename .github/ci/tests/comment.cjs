const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {test} = require('node:test');

const workflow = fs.readFileSync(`${__dirname}/../../workflows/performance.yml`, 'utf8');
const script = workflow.split('      - name: Create or update comment\n')[1]
  .split('          script: |\n')[1].replace(/^            /gm, '');
const marker = '<!-- eventjet-performance -->';

const summaries = {
  on: '## 🟢 Performance improvement (OPcache on)\n',
  off: '## 🔴 Performance regression (OPcache off)\n',
};

async function publish({comments = [], head = 'head', base = 'base', state = 'open', result = 'success',
  reports = summaries} = {}) {
  const files = Object.fromEntries(Object.entries(reports).map(([mode, text]) => [`report/${mode}/comment.md`, text]));
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
    process: {env: {COMPARISON_RESULT: result, GITHUB_RUN_ATTEMPT: '2'}},
    require: name => {
      assert.equal(name, 'node:fs');
      return {existsSync: path => path in files, readFileSync: path => files[path]};
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
  const [write] = await publish({reports: {}, result: 'failure', comments: [
    {id: 3, user: {login: 'github-actions[bot]'}, body: marker},
  ]});
  assert.equal(write.method, 'update');
  assert.match(write.body, /comparison unavailable \(OPcache on\)/);
  assert.match(write.body, /comparison unavailable \(OPcache off\)/);
});

test('keeps regression results visible even when the gate fails', async () => {
  const [write] = await publish({result: 'failure'});
  assert.match(write.body, /regression \(OPcache off\)/);
  assert.match(write.body, /performance job failed/);
});

test('shows both OPcache modes, primary first', async () => {
  const [write] = await publish();
  const on = write.body.indexOf('(OPcache on)');
  assert.ok(on >= 0 && on < write.body.indexOf('(OPcache off)'));
});

test('names the mode whose comparison is missing', async () => {
  const [write] = await publish({result: 'failure', reports: {on: summaries.on}});
  assert.match(write.body, /improvement \(OPcache on\)/);
  assert.match(write.body, /comparison unavailable \(OPcache off\)/);
});

test('rejects an oversized comment before writing', async () => {
  await assert.rejects(publish({reports: {...summaries, off: 'x'.repeat(50000)}}), /size limit/);
});
