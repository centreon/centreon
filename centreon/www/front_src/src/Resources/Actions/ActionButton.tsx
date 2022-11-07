<<<<<<< HEAD
import { Button, ButtonProps } from '@mui/material';
=======
import * as React from 'react';

import { Button, ButtonProps } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

const ActionButton = (props: ButtonProps): JSX.Element => (
  <Button color="primary" size="small" {...props} />
);

export default ActionButton;
