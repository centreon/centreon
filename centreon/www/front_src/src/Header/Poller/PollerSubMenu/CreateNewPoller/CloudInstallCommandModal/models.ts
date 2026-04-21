import { PollerEnvironment } from '../../models';

export interface CloudInstallCommandFormValues {
  environment: PollerEnvironment | null;
  pollerName: string;
  token: { id: string; name: string } | null;
}
