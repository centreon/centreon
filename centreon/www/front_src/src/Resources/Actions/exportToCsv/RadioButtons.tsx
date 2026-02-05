import {
  FormControlLabel,
  FormLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';

import { SelectEntry } from '@centreon/ui';

import { PrimitiveAtom, useAtom } from 'jotai';
import { equals } from 'ramda';
import { SyntheticEvent, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import useExportCsvStyles from './exportCsv.styles';

interface Props<T> {
  defaultChecked: PrimitiveAtom<T>;
  options: Array<SelectEntry>;
  title: string;
  getData: (label: string) => void;
}

const RadioButtons = <T extends string>({
  defaultChecked,
  options,
  title,
  getData
}: Props<T>) => {
  const { classes } = useExportCsvStyles();
  const { t } = useTranslation();

  const [checked, setChecked] = useAtom(defaultChecked);

  const change = useCallback(
    (event: SyntheticEvent<Element, Event>) => {
      if (equals(event.currentTarget.value, checked)) {
        return;
      }

      setChecked(event.currentTarget.value);

      getData(event.currentTarget.value);
    },
    [checked]
  );

  const getCheckedValue = useCallback(
    (id: string) => equals(checked, id),
    [checked]
  );

  return (
    <RadioGroup aria-labelledby={title}>
      <FormLabel id={title}>
        <Typography className={classes.subTitle} variant="subtitle2">
          {title}
        </Typography>
      </FormLabel>
      {options.map(({ id, name }) => (
        <FormControlLabel
          control={
            <Radio
              checked={getCheckedValue(id)}
              className={classes.radioInput}
              size="small"
              slotProps={{ input: { 'data-testid': name } }}
            />
          }
          key={id}
          label={t(name)}
          labelPlacement="end"
          onChange={change}
          value={id}
        />
      ))}
    </RadioGroup>
  );
};

export default RadioButtons;
