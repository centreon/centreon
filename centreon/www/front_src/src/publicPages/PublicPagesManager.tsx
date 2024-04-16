import { Suspense } from 'react';

import { FallbackPage } from '@centreon/ui';

import NotFoundPage from '../FallbackPages/NotFoundPage';
import ErrorBoundary from '../federatedModules/Load/ErrorBoundary';
import { MainLoaderWithoutTranslation } from '../Main/MainLoader';

import {
  labelPageCannotBeLoaded,
  labelSomethingWentWrong
} from './translatedLabels';
import { usePageResolver } from './usePageResolver';

const PublicPagesManager = (): JSX.Element => {
  const route = usePageResolver();

  if (!route) {
    return <NotFoundPage />;
  }
  const Component = route[1];

  return (
    <ErrorBoundary
      fallback={
        <FallbackPage
          message={labelPageCannotBeLoaded}
          title={labelSomethingWentWrong}
        />
      }
    >
      <Suspense fallback={<MainLoaderWithoutTranslation />}>
        <Component />
      </Suspense>
    </ErrorBoundary>
  );
};

export default PublicPagesManager;
