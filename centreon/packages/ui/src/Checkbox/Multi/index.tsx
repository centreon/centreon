import { includes } from 'ramda';

import { FormGroup } from '@mui/material';

import SingleCheckbox, { LabelPlacement } from '../Single';

interface Props {
  disabled?: boolean;
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
  options: Array<string>;
  row?: boolean;
  values: Array<string>;
}

const MultiCheckbox = ({
  options,
  values,
  row = false,
  onChange,
  labelPlacement = 'end',
  disabled = false
}: Props): JSX.Element => {
  return (
    <FormGroup row={row}>
      {options.map((value) => {
        return (
          <SingleCheckbox
            checked={includes(value, values)}
            disabled={disabled}
            key={value}
            label={value}
            labelPlacement={labelPlacement}
            onChange={onChange}
          />
        );
      })}
    </FormGroup>
  );
};

export default MultiCheckbox;
