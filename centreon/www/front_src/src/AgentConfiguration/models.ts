import { SelectEntry } from '@centreon/ui';

export enum AgentType {
  Telegraf = 'telegraf',
  CMA = 'centreon-agent'
}

export enum ConnectionMode {
  secure = 'secure',
  noTLS = 'no-tls',
  insecure = 'insecure'
}

export interface AgentConfigurationListing {
  id: number;
  name: string;
  type: AgentType | null;
  isAgentInitiated?: boolean;
  pollers: Array<{
    id: number;
    name: string;
    isCentral?: boolean;
  }>;
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
  id: number;
  name: string;
  address: string;
  port: number;
  pollerCaCertificate: string | null;
  pollerCaName: string | null;
  token?: {
    id: string;
    name: string;
    creatorId?: number;
    token_name?: string;
  } | null;
}

export interface Token {
  id: number;
  name: string;
  creatorId: number;
}

export interface CMAConfiguration {
  port?: number | null;
  agentInitiated: boolean;
  pollerInitiated: boolean;
  otelPublicCertificate: string | null;
  otelCaCertificate: string | null;
  otelPrivateKey: string | null;
  hosts: Array<HostConfiguration>;
  tokens?: Array<Token>;
  createHostAuto?: boolean;
}

export interface TelegrafConfigurationAPI {
  otel_public_certificate: string | null;
  otel_ca_certificate: string | null;
  otel_private_key: string | null;
  conf_server_port: string | number;
  conf_certificate: string | null;
  conf_private_key: string | null;
}

export interface HostConfigurationToAPI {
  address: string;
  id?: number;
  port: number;
  poller_ca_certificate: string | null;
  poller_ca_name: string | null;
  token?: { creator_id?: number; name?: string } | null;
}

export interface CMAConfigurationAPI {
  agent_initiated: boolean;
  poller_initiated: boolean;
  otel_public_certificate: string | null;
  otel_ca_certificate: string | null;
  otel_private_key: string | null;
  hosts: Array<HostConfigurationToAPI>;
  port?: number | null;
  tokens?: Array<{ name: string; creator_id: number }>;
  create_host_auto?: boolean;
}

export interface AgentConfiguration
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: AgentType;
  connectionMode: ConnectionMode;
}

export interface AgentConfigurationForm
  extends Omit<AgentConfigurationListing, 'id' | 'type'> {
  configuration: TelegrafConfiguration | CMAConfiguration;
  type: SelectEntry | null;
  connectionMode: { id: ConnectionMode; name: string };
}

export interface AgentConfigurationAPI
  extends Omit<AgentConfigurationListing, 'id' | 'pollers' | 'type'> {
  type?: AgentType;
  configuration: TelegrafConfigurationAPI | CMAConfigurationAPI;
  connection_mode?: string;
  poller_ids: Array<number>;
}

export enum FormVariant {
  Add = 0,
  Update = 1
}

export interface InstallationCommand {
  windowsScriptCommand: string;
  linuxScriptCommand: string;
}
