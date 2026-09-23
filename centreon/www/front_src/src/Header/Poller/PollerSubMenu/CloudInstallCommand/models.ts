export enum PollerEnvironment {
  VM = 'vm',
  // Displayed as "Container", but the value is the persisted `poller_type` the
  // API expects — see PollerTypeEnum on the backend. Do not rename it.
  Container = 'docker'
}

export interface CloudInstallCommandFormValues {
  environment: PollerEnvironment | null;
  pollerAddress: string;
  pollerName: string;
  centralAddress?: string;
  token: { id: string; name: string } | null;
}
