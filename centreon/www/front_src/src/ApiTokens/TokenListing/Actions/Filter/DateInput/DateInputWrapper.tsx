import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  labelCreationDate,
  labelExpirationDate
} from '../../../../translatedLabels';
import { creationDateAtom, expirationDateAtom } from '../atoms';
import { Property } from '../models';

import DateFilter from './DateFilter';

const DateInputWrapper = (): JSX.Element => {
  const { t } = useTranslation();
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);

  const dataCreationDate = useMemo(
    () => ({ date: creationDate, setDate: setCreationDate }),
    [creationDate]
  );

  const dataExpirationDate = useMemo(
    () => ({ date: expirationDate, setDate: setExpirationDate }),
    [expirationDate]
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
