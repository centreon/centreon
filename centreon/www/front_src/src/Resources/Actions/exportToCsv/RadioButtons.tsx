import { SelectEntry } from '@centreon/ui';
import {
  FormControlLabel,
  FormLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';
import { PrimitiveAtom, useAtom } from 'jotai';
import { equals } from 'ramda';
import { SyntheticEvent, useCallback } from 'react';
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
        <Typography variant="subtitle2" sx={{ paddingBottom: 0.5 }}>
          {title}
        </Typography>
      </FormLabel>
      {options.map(({ id, name }) => (
        <FormControlLabel
          key={id}
          value={id}
          control={
            <Radio
              checked={getCheckedValue(id)}
              size="small"
              slotProps={{ input: { 'data-testid': name } }}
              className={classes.radioInput}
            />
          }
          labelPlacement="end"
          onChange={change}
          label={name}
        />
      ))}
    </RadioGroup>
  );
};

export default RadioButtons;
