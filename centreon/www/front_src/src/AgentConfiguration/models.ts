import { SelectEntry } from '@centreon/ui';

export enum AgentType {
  Telegraf = 'telegraf',
  CMA = 'centreon-agent'
}

export enum ConnectionMode {
  secure = 'secure',
  noTLS = 'no-tls'
}

export interface AgentConfigurationListing {
  id: number;
  name: string;
  type: AgentType | null;
  pollers: Array<SelectEntry>;
}

export interface TelegrafConfiguration {
  otelPublicCertificate: string | null;
  otelCaCertificate: string | null;
  otelPrivateKey: string | null;
  confServerPort: string | number;
  confCertificate: string | null;
  confPrivateKey: string | null;
}

export interface HostConfiguration {
  address: string;
  port: number;
  pollerCaCertificate: string | null;
  pollerCaName: string | null;
}

export interface CMAConfiguration {
  isReverse: boolean;
  otelPublicCertificate: string | null;
  otelCaCertificate: string | null;
  otelPrivateKey: string | null;
  hosts: Array<HostConfiguration>;
}

export interface TelegrafConfigurationAPI {
  otel_public_certificate: string | null;
  otel_ca_certificate: string | null;
  otel_private_key: string | null;
  conf_server_port: string | number;
  conf_certificate: string | null;
  conf_private_key: string | null;
  connection_mode: string;
}

export interface HostConfigurationToAPI {
  address: string;
  port: number;
  poller_ca_certificate: string | null;
  poller_ca_name: string | null;
}

export interface CMAConfigurationAPI {
  is_reverse: boolean;
  otel_public_certificate: string | null;
  otel_ca_certificate: string | null;
  otel_private_key: string | null;
  hosts: Array<HostConfigurationToAPI>;
  connection_mode: string;
}

export interface AgentConfiguration
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: AgentType;
  connectionMode: string;
}

export interface AgentConfigurationForm
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: SelectEntry | null;
}

export interface AgentConfigurationAPI
  extends Omit<AgentConfigurationListing, 'id' | 'pollers'> {
  configuration: TelegrafConfigurationAPI | CMAConfigurationAPI;
  poller_ids: Array<number>;
}

export enum FormVariant {
  Add = 0,
  Update = 1
}
