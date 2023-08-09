import { useEffect } from 'react';

import dayjs from 'dayjs';
import { path, pipe, propEq, find, lt, isNil } from 'ramda';

import { useFetchQuery, useSnackbar } from '..';

import { labelLicenseWarning } from './translatedLabel';

const legacyBaseEndpoint = './api/internal.php';
const extensionsEndpoint = `${legacyBaseEndpoint}?object=centreon_module&action=list`;

interface Props {
  module: string;
}

export const useLicenseExpirationWarning = ({ module }: Props): void => {
  const { showWarningMessage } = useSnackbar();

  const { fetchQuery } = useFetchQuery({
    getEndpoint: () => extensionsEndpoint,
    getQueryKey: () => [module]
  });

  const currentDate = dayjs();

  const getExpirationDate = pipe(
    path(['result', 'module', 'entities']),
    find(propEq('id', module)),
    path(['license', 'expiration_date'])
  ) as (data) => string;

  useEffect(() => {
    fetchQuery().then((response) => {
      const expirationDate = getExpirationDate(response);
      if (isNil(expirationDate)) {
        return;
      }

      const daysUntilExpiration = dayjs(expirationDate).diff(
        currentDate,
        'day'
      );

      if (lt(daysUntilExpiration, 15)) {
        showWarningMessage(labelLicenseWarning(module, daysUntilExpiration));
      }
    });
  }, []);
};
