import { ChangeEvent, ReactElement } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Radio, RadioGroup } from '@mui/material';

const options = [
  {
    id: 1,
    name: 'Notifications'
  },
  {
    id: 2,
    name: 'Checks'
  },
  {
    id: 3,
    name: 'Miscellaneous'
  },
  {
    id: 4,
    name: 'Discovery'
  }
];

const CommandType = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext();

  const value = values?.commandtype || options[0];

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('options.commandtype', true);
    setFieldValue('options.commandtype', event.target.value);
  };

  return (
    <RadioGroup value={value} onChange={change} row>
      {options.map(({ id, name }) => (
        <FormControlLabel
          aria-label={t(name)}
          checked={equals(id, value)}
          control={<Radio />}
          disabled={false}
          key={id}
          label={t(name)}
          value={id}
        />
      ))}
    </RadioGroup>
  );
};

export default CommandType;
