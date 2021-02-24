import * as React from 'react';

import {
  useTheme,
  Checkbox as MuiCheckbox,
  CheckboxProps,
} from '@material-ui/core';

const Checkbox = (
  props: Omit<CheckboxProps, 'size' | 'color'>,
): JSX.Element => {
  const theme = useTheme();

  return (
    <MuiCheckbox
      size="small"
      color="primary"
      style={{ padding: theme.spacing(0.5) }}
      {...props}
    />
  );
};

export default Checkbox;
