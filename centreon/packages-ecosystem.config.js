module.exports = {
  apps: [
    {
      args: 'build:lib:watch',
      cwd: 'packages/ui-context',
      name: 'ui-context',
      script: 'pnpm'
    },
    {
      args: 'build:lib:watch',
      cwd: 'packages/ui',
      name: 'ui',
      script: 'pnpm'
    }
  ]
};
