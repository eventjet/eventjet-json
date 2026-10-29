const latestExecution = require('./latest-execution.cjs');

module.exports = async function commentArtifacts({github, context}) {
  const parameters = {...context.repo, run_id: context.runId, per_page: 100};
  const [jobs, artifacts] = await Promise.all([
    github.paginate(github.rest.actions.listJobsForWorkflowRun, {...parameters, filter: 'all'}),
    github.paginate(github.rest.actions.listWorkflowRunArtifacts, parameters),
  ]);
  return Object.fromEntries(['on', 'off'].map(mode => {
    const execution = latestExecution(jobs, `Compare decoder performance (OPcache ${mode})`);
    if (!execution) {
      return [mode, ''];
    }
    const artifactName = `performance-comment-${execution.run_attempt}-${mode}`;
    const matches = artifacts.filter(artifact => artifact.name === artifactName && !artifact.expired);
    return [mode, matches.length === 1 ? String(matches[0].id) : ''];
  }));
};
