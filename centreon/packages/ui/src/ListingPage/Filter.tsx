import * as React from 'react';

import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { useMemoComponent } from '..';

const useStyles = makeStyles()((theme) => ({
  content: {
    border: 'none',
    padding: theme.spacing(1, 0)
  }
}));

export interface FilterProps {
  content?: React.ReactElement;
}

const Filter = React.forwardRef(
  ({ content }: FilterProps, ref): JSX.Element => {
    const { classes } = useStyles();

    return (
      <Box
        className={classes.content}
        component="div"
        ref={ref as React.RefObject<HTMLDivElement>}
      >
        {content}
      </Box>
    );
  }
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
    memoProps: [...memoProps]
  });

export default Filter;
