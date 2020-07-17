import * as React from 'react';

import {
  makeStyles,
  IconButton as MuiIconButton,
  IconButtonProps,
  Tooltip,
} from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  button: {
    padding: theme.spacing(0.25),
  },
}));

type Props = {
  title: string;
  onClick: (event) => void;
  ariaLabel?: string;
} & IconButtonProps;

const IconButton = ({ title, ariaLabel, ...props }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Tooltip title={title} aria-label={ariaLabel}>
      <span>
        <MuiIconButton className={classes.button} color="primary" {...props} />
      </span>
    </Tooltip>
  );
};

export default IconButton;
