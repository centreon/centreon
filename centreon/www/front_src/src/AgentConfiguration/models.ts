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
  otelServerAddress: string;
  otelServerPort: number | string;
  otelPublicCertificate: string;
  otelCaCertificate: string;
  otelPrivateKey: string;
  confServerPort: string | number;
  confCertificate: string;
  confPrivateKey: string;
}

export interface CMAConfiguration {
  isReverse: boolean;
  otlpReceiverAddress: string;
  otlpReceiverPort: number;
  otlpCertificate: string;
  otlpCaCertificate: string;
  otlpPrivateKey: string;
  hosts: Array<{
    address: string;
    port: number;
    certificate: string;
    key: string;
  }>;
}

export interface TelegrafConfigurationAPI {
  otel_server_address: string;
  otel_server_port: number | string;
  otel_public_certificate: string;
  otel_ca_certificate: string;
  otel_private_key: string;
  conf_server_port: string | number;
  conf_certificate: string;
  conf_private_key: string;
}

export interface HostConfiguration {
  address: string;
  port: number;
  certificate: string;
  key: string;
}

export interface CMAConfigurationAPI {
  is_reverse: boolean;
  otlp_receiver_address: string;
  otlp_receiver_port: number;
  otlp_certificate: string;
  otlp_ca_certificate: string;
  otlp_private_key: string;
  hosts: Array<HostConfiguration>;
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
