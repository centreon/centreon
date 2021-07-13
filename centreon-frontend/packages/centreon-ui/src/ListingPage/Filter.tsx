import * as React from 'react';

import { Paper, makeStyles } from '@material-ui/core';

import { useMemoComponent } from '..';

const useStyles = makeStyles((theme) => ({
  content: {
    padding: theme.spacing(1),
  },
}));

export interface FilterProps {
  content?: React.ReactElement;
}

const Filter = React.forwardRef(
  ({ content }: FilterProps, ref): JSX.Element => {
    const classes = useStyles();

    return (
      <Paper square className={classes.content} ref={ref}>
        {content}
      </Paper>
    );
  },
);

interface MemoizedFilterProps extends FilterProps {
  memoProps?: Array<unknown>;
}

export const MemoizedFilter = ({
  memoProps = [],
  ...props
}: MemoizedFilterProps): JSX.Element =>
  useMemoComponent({
    Component: <Filter {...props} />,
    memoProps: [...memoProps],
  });

export default Filter;
