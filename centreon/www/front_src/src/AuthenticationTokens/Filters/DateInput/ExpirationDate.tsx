import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { labelExpirationDate } from '../../translatedLabels';

import { filtersAtom } from '../../atoms';
import { Property } from '../models';
import DateFilter from './DateFilter';

const ExpirationDate = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const setExpirationDate = (expirationDate): void => {
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
