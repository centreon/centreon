import { useFetchQuery, useSnackbar } from '@centreon/ui';
import { includes } from 'ramda';

export const useSearchActions = (search: string) => {
  const { showInfoMessage, showSuccessMessage, showErrorMessage } =
    useSnackbar();

  const { fetchQuery } = useFetchQuery({
    getQueryKey: () => ['global-search', 'export-configuration'],
    getEndpoint: () => '/configuration/monitoring-servers/generate-and-reload',
    queryOptions: {
      enabled: false
    }
  });

  const actions = [
    {
      label: 'Export & reload the configuration',
      action: () => {
        showInfoMessage(
          'Exporting and reloading the configuration. Please wait...'
        );

        fetchQuery().then((data) => {
          if (data.isError) {
            showErrorMessage('Failed to export the configuration');
            return;
          }

          showSuccessMessage('Configuration exported and reloaded');
        });
      }
    }
  ];

  const searchedActions = actions.filter(({ label }) =>
    includes(search.toLowerCase(), label.toLowerCase())
  );

  return searchedActions;
};
