import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { creationDateAtom, expirationDateAtom } from '../atoms';
import { Property } from '../models';
import {
  labelCreationDate,
  labelExpirationDate
} from '../../../../translatedLabels';

import DateInput from './DateInput';
import CustomField from './CustomField';

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
      {/* <DateInput
        dataDate={dataCreationDate}
        label={t(labelCreationDate)}
        property={Property.last}
      /> */}

      <CustomField
        dataDate={dataCreationDate}
        label={t(labelCreationDate)}
        property={Property.last}
      />
      {/* <DateInput /> */}
    </>
  );
};

export default DateInputWrapper;
