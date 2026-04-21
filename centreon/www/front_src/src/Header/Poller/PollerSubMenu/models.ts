export enum PollerEnvironment {
  VM = 'vm',
  Docker = 'docker'
}

export interface CloudPollerRegistrationCommand {
  command: string;
}

export interface CreatedPoller {
  id: number;
  uuid: string;
}
