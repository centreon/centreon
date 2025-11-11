import { ChangeEvent, ReactElement } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Radio, RadioGroup } from '@mui/material';
import { Command } from '../../models';

const options = [
  {
    id: 'Notification',
    name: 'Notification'
  },
  {
    id: 'Check',
    name: 'Check'
  },
  {
    id: 'Miscellaneous',
    name: 'Miscellaneous'
  },
  {
    id: 'Discovery',
    name: 'Discovery'
  }
];

const CommandType = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<Command>();

  const value = values?.type;

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('type', true);
    setFieldValue('type', event.target.value);
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
