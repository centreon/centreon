import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { creationDateAtom, expirationDateAtom } from '../atoms';
import { Property } from '../models';
import {
  labelCreationDate,
  labelExpirationDate
} from '../../../../translatedLabels';

import DateInput from './DateInput';

const DateInputWrapper = (): JSX.Element => {
  const { t } = useTranslation();
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);

  return (
    <>
      <DateInput
        dataDate={{ date: creationDate, setDate: setCreationDate }}
        label={t(labelCreationDate)}
        property={Property.last}
      />
      <DateInput
        dataDate={{ date: expirationDate, setDate: setExpirationDate }}
        label={t(labelExpirationDate)}
        property={Property.in}
      />
    </>
  );
};

export default DateInputWrapper;
