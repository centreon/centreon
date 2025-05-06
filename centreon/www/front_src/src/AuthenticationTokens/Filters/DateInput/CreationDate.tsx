import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { labelCreationDate } from '../../translatedLabels';

import { filtersAtom } from '../../atoms';
import { Property } from '../models';
import DateFilter from './DateFilter';

const CreationDate = (): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const setCreationDate = (creationDate): void => {
    setFilters({ ...filters, creationDate });
  };

  const dataCreationDate = {
    date: filters.creationDate,
    setDate: setCreationDate
  };

  return (
    <DateFilter
      dataDate={dataCreationDate}
      label={t(labelCreationDate)}
      property={Property.last}
    />
  );
};

export default CreationDate;
