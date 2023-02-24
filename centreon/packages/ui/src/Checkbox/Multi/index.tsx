import { SvgIconComponent } from '@mui/icons-material';
import { FormGroup } from '@mui/material';

import SingleCheckbox, { LabelPlacement } from '../Single';

interface Item {
  Icon?: SvgIconComponent;
  checked: boolean;
  label: string;
}

interface Props {
  labelPlacement?: LabelPlacement;
  onChange?: () => void;
  row?: boolean;
  values: Array<Item>;
}

const MultiCheckbox = ({
  values,
  row = false,
  onChange,
  labelPlacement = 'end'
}: Props): JSX.Element => {
  return (
    <FormGroup row={row}>
      {values?.map(({ label, checked, Icon }) => {
        return (
          <SingleCheckbox
            Icon={Icon}
            checked={checked}
            key={label}
            label={label}
            labelPlacement={labelPlacement}
            onChange={onChange}
          />
        );
      })}
    </FormGroup>
  );
};

export default MultiCheckbox;
