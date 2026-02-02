import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { Command, CommandsListItem, Plugin } from '../models';

const JSONLDEnityListDecoder = JsonDecoder.object<{ id: string; name: string }>(
  {
    id: JsonDecoder.string,
    name: JsonDecoder.string
  },
  'Entity',
  { id: '@id' }
);

const commandsDecoder = JsonDecoder.object<CommandsListItem>(
  {
    commandLine: JsonDecoder.string,
    hostsCount: JsonDecoder.number,
    hostTemplatesCount: JsonDecoder.number,
    id: JsonDecoder.number,
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    servicesCount: JsonDecoder.number,
    serviceTemplatesCount: JsonDecoder.number,
    type: JsonDecoder.string,
    isFromMonitoringConnectors: JsonDecoder.optional(JsonDecoder.boolean)
  },
  'Command',
  {
    commandLine: 'command_line',
    hostsCount: 'used_hosts_count',
    hostTemplatesCount: 'used_host_templates_count',
    isActivated: 'is_activated',
    servicesCount: 'used_services_count',
    serviceTemplatesCount: 'used_service_templates_count',
    isFromMonitoringConnectors: 'is_from_monitoring_connectors'
  }
);

export const commandsListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: commandsDecoder,
  entityDecoderName: 'Command',
  listingDecoderName: 'Commands List'
});

export const commandDecoder = JsonDecoder.object<Command>(
  {
    commandLine: JsonDecoder.string,
    comment: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    connector: JsonDecoder.optional(
      JsonDecoder.nullable(JSONLDEnityListDecoder)
    ),
    isShellEnabled: JsonDecoder.boolean,
    name: JsonDecoder.string,
    type: JsonDecoder.string
  },
  'Command',
  {
    commandLine: 'command_line',
    isShellEnabled: 'is_shell_enabled'
  }
);

export const JSONLDEntitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JSONLDEnityListDecoder,
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});

export const pluginDetailsDecoder = JsonDecoder.object<Plugin>(
  {
    commandLine: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    name: JsonDecoder.string
  },
  'Plugin',
  {
    commandLine: 'command_line'
  }
);
