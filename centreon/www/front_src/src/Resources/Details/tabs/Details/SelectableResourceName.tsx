<<<<<<< HEAD
import { Typography, TypographyVariant } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { makeStyles, Typography, TypographyVariant } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles(() => ({
  name: {
    cursor: 'pointer',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
}));

interface Props {
  name: string;
  onSelect: () => void;
  variant?: TypographyVariant;
}

const SelectableResourceName = ({
  name,
  onSelect,
  variant = 'body1',
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Typography className={classes.name} variant={variant} onClick={onSelect}>
      {name}
    </Typography>
  );
};

export default SelectableResourceName;
