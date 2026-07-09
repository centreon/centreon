export enum PollerEnvironment {
  VM = 'vm',
  Docker = 'docker'
}

export interface CloudInstallCommandFormValues {
  environment: PollerEnvironment | null;
  pollerAddress: string;
  pollerName: string;
  token: { id: string; name: string } | null;
}
