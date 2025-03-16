import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { labelCreationDate, labelExpirationDate } from '../../translatedLabels';

import { filtersAtom } from '../../atoms';
import { Property } from '../models';
import DateFilter from './DateFilter';

const DateInputWrapper = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const setCreationDate = (creationDate): void => {
    setFilters({ ...filters, creationDate });
  };

  const setExpirationDate = (expirationDate): void => {
    setFilters({ ...filters, expirationDate });
  };

  const dataCreationDate = useMemo(
    () => ({ date: filters.creationDate, setDate: setCreationDate }),
    [filters.creationDate]
  );

  const dataExpirationDate = useMemo(
    () => ({ date: filters.expirationDate, setDate: setExpirationDate }),
    [filters.expirationDate]
  );

  return (
    <>
      <DateFilter
        dataDate={dataCreationDate}
        label={t(labelCreationDate)}
        property={Property.last}
      />

      <DateFilter
        dataDate={dataExpirationDate}
        label={t(labelExpirationDate)}
        property={Property.in}
      />
    </>
  );
};

export default DateInputWrapper;
