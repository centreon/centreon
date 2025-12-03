import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

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
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    isActivated: JsonDecoder.boolean,
    hostsCount: JsonDecoder.number,
    hostTemplatesCount: JsonDecoder.number,
    servicesCount: JsonDecoder.number,
    serviceTemplatesCount: JsonDecoder.number,
    type: JsonDecoder.string,
    commandLine: JsonDecoder.string
  },
  'Command',
  {
    isActivated: 'is_activated',
    hostsCount: 'used_hosts_count',
    hostTemplatesCount: 'used_host_templates_count',
    servicesCount: 'used_services_count',
    serviceTemplatesCount: 'used_service_templates_count',
    commandLine: 'command_line'
  }
);

export const commandsListDecoder = buildListingDecoder({
  entityDecoder: commandsDecoder,
  entityDecoderName: 'Command',
  listingDecoderName: 'Commands List',
  apiFormat: 'JSON-LD'
});

export const commandDecoder = JsonDecoder.object<Command>(
  {
    name: JsonDecoder.string,
    type: JsonDecoder.string,
    commandLine: JsonDecoder.string,
    comment: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    isShellEnabled: JsonDecoder.boolean,
    connector: JsonDecoder.optional(
      JsonDecoder.nullable(JSONLDEnityListDecoder)
    )
  },
  'Command',
  {
    commandLine: 'command_line',
    isShellEnabled: 'is_shell_enabled'
  }
);

export const JSONLDEntitiesListDecoder = buildListingDecoder({
  entityDecoder: JSONLDEnityListDecoder,
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List',
  apiFormat: 'JSON-LD'
});

export const pluginDetailsDecoder = JsonDecoder.object<Plugin>(
  {
    name: JsonDecoder.string,
    commandLine: JsonDecoder.string,
    description: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
  },
  'Plugin',
  {
    commandLine: 'command_line'
  }
);
