import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { any, complement, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import fanOut from '../../ConfigurationBase/api/fanOut';
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
    fanOut(
      rows.map(({ id }) => id as number),
      (id) => mutateAsync({ _meta: { id }, payload: {} })
    )
      .then(({ results }) => {
        if (any(complement(propEq(204, 'status')), results)) {
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
