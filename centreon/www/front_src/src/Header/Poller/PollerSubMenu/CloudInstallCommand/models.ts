export enum PollerEnvironment {
  VM = 'vm',
  Docker = 'docker'
}

export interface CloudInstallCommandFormValues {
  environment: PollerEnvironment | null;
  pollerName: string;
  token: { id: string; name: string } | null;
}
