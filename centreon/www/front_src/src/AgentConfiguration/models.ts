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

export interface CMAConfiguration {
  isReverse: boolean;
  otlpCertificate: string;
  otlpCaCertificate: string | null;
  otlpCaCertificateName: string | null;
  otlpPrivateKey: string;
  hosts: Array<{
    address: string;
    port: number;
    certificate: string;
    key: string;
  }>;
}

export interface TelegrafConfigurationAPI {
  otel_public_certificate: string;
  otel_ca_certificate: string | null;
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
  otlp_certificate: string;
  otlp_ca_certificate: string | null;
  otlp_ca_certificate_name: string | null;
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
