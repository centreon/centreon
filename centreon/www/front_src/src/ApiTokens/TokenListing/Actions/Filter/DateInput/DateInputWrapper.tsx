import { useAtom } from 'jotai';

import { creationDateAtom, expirationDateAtom } from '../atoms';
import { Property } from '../models';

import DateInput from './DateInput';

const DateInputWrapper = (): JSX.Element => {
  const [creationDate, setCreationDate] = useAtom(creationDateAtom);
  const [expirationDate, setExpirationDate] = useAtom(expirationDateAtom);

  return (
    <>
      <DateInput
        dataDate={{ date: creationDate, setDate: setCreationDate }}
        label="Creation date"
        property={Property.last}
      />
      <DateInput
        dataDate={{ date: expirationDate, setDate: setExpirationDate }}
        label="Expiration date"
        property={Property.in}
      />
    </>
  );
};

export default DateInputWrapper;
