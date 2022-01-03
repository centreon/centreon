import * as React from 'react';

import { isNil } from 'ramda';

import { Checkbox, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles((theme) => ({
  checkbox: {
    marginRight: theme.spacing(1),
    padding: 0,
  },
  container: {
    alignItems: 'center',
    display: 'flex',
  },
}));

interface Props {
  checkboxSelected?: boolean;
  children: string;
}

const Option = React.forwardRef(
  ({ children, checkboxSelected }: Props, ref): JSX.Element => {
    const classes = useStyles();

    return (
      <div
        className={classes.container}
        ref={ref as React.RefObject<HTMLDivElement>}
      >
        {!isNil(checkboxSelected) && (
          <Checkbox
            checked={checkboxSelected}
            className={classes.checkbox}
            color="primary"
            size="small"
          />
        )}
        <Typography variant="body2">{children}</Typography>
      </div>
    );
  },
);

export default Option;
