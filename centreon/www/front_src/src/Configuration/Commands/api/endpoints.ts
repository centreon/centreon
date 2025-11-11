import { buildListingEndpoint } from '@centreon/ui';

export const commandsEndpoint = '/configuration/commands';

export const getCommandsEndpoint = ({ id }): string =>
  `${commandsEndpoint}/${id}`;

const globalMacrosEndpoint = '/configuration/global-macros';
const standardMacrosEndpoint = '/configuration/standard-macros';
const pluginsEndpoint = '/configuration/plugins';

export const geListEndpoint =
  (baseEndpoint: string) =>
  ({ search, page }): string =>
    buildListingEndpoint({
      baseEndpoint: baseEndpoint,
      parameters: {
        itemsPerPage: 10,
        page,
        search
      }
    });

export const getGlobalMacrosEndpoint = geListEndpoint(globalMacrosEndpoint);
export const getStandardMacrosEndpoint = geListEndpoint(standardMacrosEndpoint);
export const getPluginsEndpoint = geListEndpoint(pluginsEndpoint);

export const connectorsEndpoint = '/configuration/connectors';
