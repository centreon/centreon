import React from 'react';

import clsx from 'clsx';

import { CircularProgress, makeStyles } from '@material-ui/core';

const useStyles = makeStyles(() => ({
  alignCenter: {
    width: '100%',
    height: '100%',
    display: 'grid',
    justifyContent: 'center',
    alignItems: 'center',
  },
}));

interface Props {
  loading: boolean;
  children: React.ReactElement;
  alignCenter?: boolean;
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
