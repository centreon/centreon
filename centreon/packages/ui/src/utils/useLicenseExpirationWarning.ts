import dayjs from 'dayjs';
import { isNil, lt, path } from 'ramda';
import { useEffect } from 'react';
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

  const getExpirationDate = (obj: unknown): string => {
    const entities = path(['result', 'module', 'entities'], obj) as
      | Array<Record<string, unknown>>
      | undefined;
    const entity = entities ? entities.find((e) => e.id === module) : undefined;
    return path(
      ['license', 'expiration_date'],
      entity as Record<string, unknown>
    ) as string;
  };

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
  }, [data, currentDate, getExpirationDate, module, showWarningMessage, t]);
};
