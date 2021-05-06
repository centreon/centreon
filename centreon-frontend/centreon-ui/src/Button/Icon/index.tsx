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
