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

export interface AgentConfigurationConfigurationAPI {
  otel_server_address: string;
  otel_server_port: number | string;
  otel_public_certificate: string;
  otel_ca_certificate: string;
  otel_private_key: string;
  conf_server_port: string | number;
  conf_certificate: string;
  conf_private_key: string;
}

export interface AgentConfiguration
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: AgentConfigurationConfiguration;
  type: AgentType;
}

export interface AgentConfigurationForm
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: AgentConfigurationConfiguration;
  type: SelectEntry | null;
}

export interface AgentConfigurationAPI
  extends Omit<AgentConfigurationListing, 'id' | 'pollers'> {
  configuration: AgentConfigurationConfigurationAPI;
  pollers: Array<number>;
}

export enum FormVariant {
  Add = 0,
  Update = 1
}
