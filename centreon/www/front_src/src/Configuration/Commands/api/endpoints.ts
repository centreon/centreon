import { buildListingEndpoint } from '@centreon/ui';

export const commandsEndpoint = '/configuration/commands';

export const getCommandEndpoint = ({ id }): string =>
  `${commandsEndpoint}/${id}`;

export const globalMacrosEndpoint = '/configuration/global-macros';
export const standardMacrosEndpoint = '/configuration/standard-macros';
export const pluginsEndpoint = '/configuration/plugins';

export const getListEndpoint =
  (baseEndpoint: string) =>
  ({ search, page }): string => {
    const customQueryParameters = search
      ? [
          {
            name: 'name[lk]',
            value: search.conditions[0].values.$lk.slice(1, -1)
          }
        ]
      : [];

    return buildListingEndpoint({
      apiFormat: 'JSON-LD',
      baseEndpoint: baseEndpoint,
      parameters: {
        limit: 10,
        page
      },
      customQueryParameters
    });
  };

export const getGlobalMacrosEndpoint = getListEndpoint(globalMacrosEndpoint);
export const getStandardMacrosEndpoint = getListEndpoint(
  standardMacrosEndpoint
);
export const getPluginsEndpoint = getListEndpoint(pluginsEndpoint);

export const connectorsEndpoint = '/configuration/connectors';

export const duplicateCommandsEndpoint = '/configuration/commands/_duplicate';

export const getPluginEndpoint = ({ id }) => `/configuration/plugins/${id}`;
