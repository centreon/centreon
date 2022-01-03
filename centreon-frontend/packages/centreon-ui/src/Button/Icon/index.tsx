import * as React from 'react';

import {
  IconButton as MuiIconButton,
  IconButtonProps,
  Tooltip,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles((theme) => ({
  button: {
    padding: theme.spacing(0.25),
  },
}));

type Props = {
  ariaLabel?: string;
  onClick: (event) => void;
  title?: string;
} & IconButtonProps;

const IconButton = ({
  title = '',
  ariaLabel,
  ...props
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Tooltip aria-label={ariaLabel} title={title}>
      <span>
        <MuiIconButton className={classes.button} color="primary" {...props} />
      </span>
    </Tooltip>
  );
};

export default IconButton;
