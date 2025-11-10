import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Command, CommandsListItem } from '../models';

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
    comment: JsonDecoder.string,
    isShellEnabled: JsonDecoder.boolean,
    connector: JsonDecoder.object(
      {
        id: JsonDecoder.number,
        name: JsonDecoder.string
      },
      'connector'
    )
  },
  'Command',
  {
    commandLine: 'command_line',
    isShellEnabled: 'is_shell_enabled'
  }
);
