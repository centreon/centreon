import {
  FormControlLabel,
  FormLabel,
  Radio,
  RadioGroup,
  Typography
} from '@mui/material';
import { SelectEntry } from '@centreon/ui';
import { equals } from 'ramda';
import { SyntheticEvent, useCallback, useState } from 'react';

interface Props {
  defaultChecked: string;
  options: Array<SelectEntry>;
  title: string;
  getData: (label: string) => void;
}

const RadioButtons = ({ defaultChecked, options, title, getData }: Props) => {
  const [checked, setChecked] = useState(defaultChecked);

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
          control={<Radio checked={getCheckedValue(id)} />}
          labelPlacement="end"
          onChange={change}
          label={name}
        />
      ))}
    </RadioGroup>
  );
};

export default RadioButtons;
