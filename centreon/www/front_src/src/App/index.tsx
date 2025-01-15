import { Suspense, lazy } from 'react';
import 'intl-pluralrules';

import { makeStyles } from 'tss-react/mui';

import { LoadingSkeleton, useFullscreenListener } from '@centreon/ui';

import PageLoader from '../components/PageLoader';

import useApp from './useApp';

const useStyles = makeStyles()({
  content: {
    display: 'flex',
    flexDirection: 'column',
    flexGrow: 1,
    height: '100%',
    overflow: 'hidden',
    position: 'relative'
  },
  fullScreenWrapper: {
    flexGrow: 1,
    height: '100%',
    overflow: 'hidden',
    width: '100%'
  },
  fullscreenButton: {
    bottom: '10px',
    position: 'absolute',
    right: '20px',
    zIndex: 1500
  },
  mainContent: {
    '& iframe': {
      display: 'block'
    },
    flexGrow: 1,
    overflow: 'hidden'
  },
  wrapper: {
    display: 'flex',
    height: '100%',
    overflow: 'hidden'
  }
});

const MainRouter = lazy(() => import('../components/mainRouter'));
const Header = lazy(() => import('../Header'));
const Navigation = lazy(() => import('../Navigation'));

const App = (): JSX.Element => {
  const { classes } = useStyles();
  useApp();

  const isFullscreenActivated = useFullscreenListener();

  return (
    <Suspense fallback={<PageLoader />}>
      <div className={classes.wrapper}>
        {!isFullscreenActivated && (
          <Suspense fallback={<LoadingSkeleton height="100%" width={45} />}>
            <Navigation />
          </Suspense>
        )}
        <div className={classes.content} id="content">
          <Suspense fallback={<LoadingSkeleton height={56} width="100%" />}>
            <Header />
          </Suspense>
          <div className={classes.mainContent} id="maint-content">
            <MainRouter />
          </div>
        </div>
      </div>
    </Suspense>
  );
};

export default App;
