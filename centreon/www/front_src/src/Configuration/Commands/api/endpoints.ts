import { buildListingEndpoint } from '@centreon/ui';

export const commandsEndpoint = '/configuration/commands';

export const getCommandEndpoint = ({ id }): string =>
  `${commandsEndpoint}/${id}`;

export const globalMacrosEndpoint = '/configuration/global-macros';
export const standardMacrosEndpoint = '/configuration/standard-macros';
export const pluginsEndpoint = '/configuration/plugins';

export const geListEndpoint =
  (baseEndpoint: string) =>
  ({ search, page }): string =>
    buildListingEndpoint({
      apiFormat: 'JSON-LD',
      baseEndpoint: baseEndpoint,
      parameters: {
        limit: 10,
        page,
        search
      }
    });

export const getGlobalMacrosEndpoint = geListEndpoint(globalMacrosEndpoint);
export const getStandardMacrosEndpoint = geListEndpoint(standardMacrosEndpoint);
export const getPluginsEndpoint = geListEndpoint(pluginsEndpoint);

export const connectorsEndpoint = '/configuration/connectors';
