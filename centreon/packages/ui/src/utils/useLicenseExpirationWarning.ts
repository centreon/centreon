import { useEffect } from 'react';

import dayjs from 'dayjs';
import { path, find, isNil, lt, pipe, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useFetchQuery, useSnackbar } from '..';

import { labelLicenseWarning } from './translatedLabel';

const legacyBaseEndpoint = './api/internal.php';
const extensionsEndpoint = `${legacyBaseEndpoint}?object=centreon_module&action=list`;

interface Props {
  module: string;
}

export const useLicenseExpirationWarning = ({ module }: Props): void => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const { data } = useFetchQuery({
    getEndpoint: () => extensionsEndpoint,
    getQueryKey: () => [module]
  });

  const currentDate = dayjs();

  const getExpirationDate = pipe(
    path(['result', 'module', 'entities']),
    find(propEq(module, 'id')),
    path(['license', 'expiration_date'])
  ) as (data) => string;

  useEffect(() => {
    if (isNil(data)) {
      return;
    }

    const expirationDate = getExpirationDate(data);

    if (isNil(expirationDate)) {
      return;
    }

    const daysUntilExpiration = dayjs(expirationDate).diff(currentDate, 'day');

    if (lt(daysUntilExpiration, 15)) {
      showWarningMessage(t(labelLicenseWarning(module, daysUntilExpiration)));
    }
  }, [data]);
};
