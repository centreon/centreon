import { SelectEntry } from '@centreon/ui';

export enum AgentType {
  Telegraf = 'telegraf',
  CMA = 'centreon_agent'
}

export interface AgentConfigurationListing {
  id: number;
  name: string;
  type: AgentType | null;
  pollers: Array<SelectEntry>;
}

export interface TelegrafConfiguration {
  otelPublicCertificate: string;
  otelCaCertificate: string | null;
  otelPrivateKey: string;
  confServerPort: string | number;
  confCertificate: string;
  confPrivateKey: string;
}

export interface HostConfiguration {
  address: string;
  port: number;
  pollerCaCertificate: string | null;
  pollerCaName: string | null;
}

export interface CMAConfiguration {
  isReverse: boolean;
  otelPublicCertificate: string;
  otelCaCertificate: string | null;
  otelPrivateKey: string;
  hosts: Array<HostConfiguration>;
}

export interface TelegrafConfigurationAPI {
  otel_public_certificate: string;
  otel_ca_certificate: string | null;
  otel_private_key: string;
  conf_server_port: string | number;
  conf_certificate: string;
  conf_private_key: string;
}

export interface HostConfigurationToAPI {
  address: string;
  port: number;
  poller_ca_certificate: string | null;
  poller_ca_name: string | null;
}

export interface CMAConfigurationAPI {
  is_reverse: boolean;
  otel_public_certificate: string;
  otel_ca_certificate: string | null;
  otel_private_key: string;
  hosts: Array<HostConfigurationToAPI>;
}

export interface AgentConfiguration
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: AgentType;
}

export interface AgentConfigurationForm
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: SelectEntry | null;
}

export interface AgentConfigurationAPI
  extends Omit<AgentConfigurationListing, 'id' | 'pollers'> {
  configuration: TelegrafConfigurationAPI | CMAConfigurationAPI;
  pollers: Array<number>;
}

export enum FormVariant {
  Add = 0,
  Update = 1
}
