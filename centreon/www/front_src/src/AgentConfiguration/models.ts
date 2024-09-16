import { SelectEntry } from '@centreon/ui';

export enum AgentType {
  Telegraf = 'telegraf'
}

export interface AgentConfigurationListing {
  id: number;
  name: string;
  type: AgentType | null;
  pollers: Array<SelectEntry>;
}

export interface AgentConfigurationConfiguration {
  otelServerAddress: string;
  otelServerPort: number | string;
  otelPublicCertificate: string;
  otelCaCertificate: string;
  otelPrivateKey: string;
  confServerPort: string | number;
  confCertificate: string;
  confPrivateKey: string;
}

export type AgentConfigurationConfigurationForm = Pick<
  AgentConfigurationConfiguration,
  'otelServerAddress' | 'otelServerPort' | 'confServerPort'
>;

export interface AgentConfiguration
  extends Omit<AgentConfigurationListing, 'id'> {
  configuration: AgentConfigurationConfigurationForm;
}

export enum FormVariant {
  Add = 0,
  Update = 1
}
