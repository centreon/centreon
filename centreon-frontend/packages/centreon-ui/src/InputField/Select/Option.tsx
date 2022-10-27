import { forwardRef, RefObject } from 'react';

import { isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Checkbox, Stack, Typography } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
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
  thumbnailUrl?: string;
}

const Option = forwardRef(
  ({ children, checkboxSelected, thumbnailUrl }: Props, ref): JSX.Element => {
    const { classes } = useStyles();

    return (
      <div className={classes.container} ref={ref as RefObject<HTMLDivElement>}>
        {!isNil(checkboxSelected) && (
          <Checkbox
            checked={checkboxSelected}
            className={classes.checkbox}
            color="primary"
            size="small"
          />
        )}
        <Stack alignItems="center" direction="row" spacing={1}>
          {thumbnailUrl && (
            <img alt={children} height={20} src={thumbnailUrl} width={20} />
          )}
          <Typography variant="body2">{children}</Typography>
        </Stack>
      </div>
    );
  },
);

export default Option;
