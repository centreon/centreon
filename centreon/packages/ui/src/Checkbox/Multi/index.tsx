import { includes } from 'ramda';

import { FormGroup } from '@mui/material';

import SingleCheckbox, { LabelPlacement } from '../Single';

interface Props {
  initialValues: Array<string>;
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
  row?: boolean;
  values: Array<string>;
}

const MultiCheckbox = ({
  initialValues,
  values,
  row = false,
  onChange,
  labelPlacement = 'end'
}: Props): JSX.Element => {
  return (
    <FormGroup row={row}>
      {initialValues?.map((elm) => {
        return (
          <SingleCheckbox
            checked={includes(elm, values)}
            key={elm}
            label={elm}
            labelPlacement={labelPlacement}
            onChange={onChange}
          />
        );
      })}
    </FormGroup>
  );
};

export default MultiCheckbox;
