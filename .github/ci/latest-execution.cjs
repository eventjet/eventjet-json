module.exports = function latestExecution(jobs, name) {
  const executions = jobs.filter(job => job.name === name).sort((a, b) => b.run_attempt - a.run_attempt);
  const latest = executions[0];
  if (!latest || !Number.isInteger(latest.run_attempt) || latest.run_attempt < 1 ||
      !latest.started_at || !latest.completed_at ||
      latest.status !== 'completed' || !['success', 'failure'].includes(latest.conclusion)) {
    return undefined;
  }
  // GitHub carries completed jobs into later attempts without executing them again.
  return executions.findLast(job => job.started_at === latest.started_at && job.completed_at === latest.completed_at);
};
