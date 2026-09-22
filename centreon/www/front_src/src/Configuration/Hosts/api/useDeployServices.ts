import {
  Method,
  type ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { any, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { ResourceRow } from '../../models';
import {
  labelFailedToDeployServices,
  labelServicesDeployed
} from '../translatedLabels';
import { getDeployServicesEndpoint } from './endpoints';

interface UseDeployServicesState {
  deployServices: (rows: Array<ResourceRow>) => void;
  isMutating: boolean;
}

// The endpoint deploys one host at a time, so a selection fans out into one
// request per host.
const useDeployServices = (): UseDeployServicesState => {
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<
    object,
    { id: number | string }
  >({
    // A Core route, so the default `./api/latest` base is the right one.
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
        // `customFetch` resolves with an error shape rather than rejecting, so
        // a failed deploy never reaches the catch below.
        if (any(propEq(true, 'isError'), responses as Array<ResponseError>)) {
          showErrorMessage(t(labelFailedToDeployServices));

          return;
        }

        showSuccessMessage(t(labelServicesDeployed));
      })
      .catch(() => showErrorMessage(t(labelFailedToDeployServices)));
  };

  return { deployServices, isMutating };
};

export default useDeployServices;
