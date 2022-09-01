import { ReactElement } from 'react';

import { makeStyles } from 'tss-react/mui';

import { CircularProgress } from '@mui/material';

const useStyles = makeStyles()(() => ({
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
  children: ReactElement;
  loading: boolean;
  loadingIndicatorSize?: number;
}

const ContentWithCircularLoading = ({
  loading,
  children,
  loadingIndicatorSize = undefined,
  alignCenter = true,
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();

  if (loading) {
    return (
      <div className={cx({ [classes.alignCenter]: alignCenter })}>
        <CircularProgress size={loadingIndicatorSize} />
      </div>
    );
  }

  return children;
};

export default ContentWithCircularLoading;
