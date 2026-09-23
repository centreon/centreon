import {
  Method,
  type ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { any, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { ResourceRow } from '../../models';
import { labelServicesDeployed } from '../translatedLabels';
import { getDeployServicesEndpoint } from './endpoints';

interface UseDeployServicesState {
  deployServices: (rows: Array<ResourceRow>) => void;
  isMutating: boolean;
}

// The endpoint takes one host, so a selection fans out into one request each.
const useDeployServices = (): UseDeployServicesState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<
    object,
    { id: number | string }
  >({
    getEndpoint: ({ id }) => getDeployServicesEndpoint({ id }),
    method: Method.POST
  });

  const deployServices = (rows: Array<ResourceRow>): void => {
    Promise.all(
      rows.map(({ id }) =>
        mutateAsync({ _meta: { id: id as number }, payload: {} })
      )
    )
      .then((responses) => {
        // `customFetch` resolves with an error shape rather than rejecting,
        // and `useMutationQuery` has already shown the API's message.
        if (any(propEq(true, 'isError'), responses as Array<ResponseError>)) {
          return;
        }

        showSuccessMessage(t(labelServicesDeployed));
      })
      .catch(() => undefined);
  };

  return { deployServices, isMutating };
};

export default useDeployServices;
