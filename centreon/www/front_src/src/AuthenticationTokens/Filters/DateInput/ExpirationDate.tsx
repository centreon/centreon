// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useAtom } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { filtersAtom } from '../../atoms';
import { labelExpirationDate } from '../../translatedLabels';
import { Property } from '../models';
import DateFilter from './DateFilter';

const ExpirationDate = (): ReactElement => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const setExpirationDate = (expirationDate: unknown): void => {
    setFilters({ ...filters, expirationDate });
  };

  const dataExpirationDate = {
    date: filters.expirationDate,
    setDate: setExpirationDate
  };

  return (
    <DateFilter
      dataDate={dataExpirationDate}
      label={t(labelExpirationDate)}
      property={Property.in}
    />
  );
};

export default ExpirationDate;
