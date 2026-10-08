module.exports = async function shardArtifacts({github, context, mode, sample}) {
  const parameters = {...context.repo, run_id: context.runId, per_page: 100};
  const [jobs, artifacts] = await Promise.all([
    github.paginate(github.rest.actions.listJobsForWorkflowRun, {...parameters, filter: 'all'}),
    github.paginate(github.rest.actions.listWorkflowRunArtifacts, parameters),
  ]);
  return [1, 2, 3, 4].map(shard => {
    const name = `Measure decoder performance (OPcache ${mode}, sample ${sample}, shard ${shard})`;
    const executions = jobs.filter(job => job.name === name).sort((a, b) => b.run_attempt - a.run_attempt);
    const latest = executions[0];
    if (!latest || !Number.isInteger(latest.run_attempt) || latest.run_attempt < 1 ||
        !latest.started_at || !latest.completed_at ||
        latest.status !== 'completed' || !['success', 'failure'].includes(latest.conclusion)) {
      throw new Error(`Shard ${shard} has no completed measurement execution`);
    }
    // GitHub carries completed jobs into later attempts without executing them again.
    const execution = executions.findLast(job =>
      job.started_at === latest.started_at && job.completed_at === latest.completed_at);
    const artifactName = `performance-${mode}-${context.sha}-${execution.run_attempt}-${sample}-${shard}`;
    const matches = artifacts.filter(artifact => artifact.name === artifactName && !artifact.expired);
    if (matches.length !== 1) {
      throw new Error(`Expected one artifact for the latest execution of shard ${shard}: ${artifactName}`);
    }
    return matches[0].id;
  });
};
