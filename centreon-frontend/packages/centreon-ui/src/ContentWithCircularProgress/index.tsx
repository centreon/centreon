import React from 'react';

import clsx from 'clsx';

import { CircularProgress } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles(() => ({
  alignCenter: {
    alignItems: 'center',
    display: 'grid',
    height: '100%',
    justifyContent: 'center',
    width: '100%',
  },
}));

interface Props {
  alignCenter?: boolean;
  children: React.ReactElement;
  loading: boolean;
  loadingIndicatorSize?: number;
}

const ContentWithCircularLoading = ({
  loading,
  children,
  loadingIndicatorSize = undefined,
  alignCenter = true,
}: Props): JSX.Element => {
  const classes = useStyles();

  if (loading) {
    return (
      <div className={clsx({ [classes.alignCenter]: alignCenter })}>
        <CircularProgress size={loadingIndicatorSize} />
      </div>
    );
  }

  return children;
};

export default ContentWithCircularLoading;
