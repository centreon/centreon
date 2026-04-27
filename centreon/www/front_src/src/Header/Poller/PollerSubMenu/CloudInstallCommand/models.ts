export enum PollerEnvironment {
  VM = 'vm',
  Docker = 'docker'
}

export interface CloudInstallCommandFormValues {
  environment: PollerEnvironment | null;
  pollerName: string;
  token: Array<{ id: string; name: string }>;
}
