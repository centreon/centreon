// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useAtom } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { filtersAtom } from '../../atoms';
import { labelCreationDate } from '../../translatedLabels';
import { Property } from '../models';
import DateFilter from './DateFilter';

const CreationDate = (): ReactElement => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);

  const setCreationDate = (creationDate: unknown): void => {
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
