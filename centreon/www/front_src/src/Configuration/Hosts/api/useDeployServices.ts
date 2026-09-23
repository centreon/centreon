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
  labelServiceDeploymentFailed,
  labelServicesDeployed
} from '../translatedLabels';
import { getDeployServicesEndpoint } from './endpoints';

interface UseDeployServicesState {
  deployServices: (rows: Array<ResourceRow>) => void;
  isMutating: boolean;
}

// The endpoint takes one host, so a selection fans out into one request each.
const useDeployServices = (): UseDeployServicesState => {
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { mutateAsync, isMutating } = useMutationQuery<
    object,
    { id: number | string }
  >({
    getEndpoint: ({ id }) => getDeployServicesEndpoint({ id }),
    // Every failure this endpoint can return, so the agreed message is the only
    // one shown rather than stacking on the API's own.
    httpCodesBypassErrorSnackbar: [403, 404, 500],
    method: Method.POST
  });

  const deployServices = (rows: Array<ResourceRow>): void => {
    Promise.all(
      rows.map(({ id }) =>
        mutateAsync({ _meta: { id: id as number }, payload: {} })
      )
    )
      .then((responses) => {
        // `customFetch` resolves with an error shape rather than rejecting.
        if (any(propEq(true, 'isError'), responses as Array<ResponseError>)) {
          showErrorMessage(t(labelServiceDeploymentFailed));

          return;
        }

        showSuccessMessage(t(labelServicesDeployed));
      })
      .catch(() => showErrorMessage(t(labelServiceDeploymentFailed)));
  };

  return { deployServices, isMutating };
};

export default useDeployServices;
