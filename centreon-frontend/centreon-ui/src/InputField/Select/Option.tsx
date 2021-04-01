import * as React from 'react';

import { isNil } from 'ramda';

import { Checkbox, makeStyles, Typography } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    marginRight: theme.spacing(1),
    padding: 0,
  },
}));

interface Props {
  checkboxSelected?: boolean;
  children: string;
}

const Option = ({ children, checkboxSelected }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <>
      {!isNil(checkboxSelected) && (
        <Checkbox
          checked={checkboxSelected}
          className={classes.checkbox}
          color="primary"
          size="small"
        />
      )}
      <Typography variant="body1">{children}</Typography>
    </>
  );
};

export default Option;
