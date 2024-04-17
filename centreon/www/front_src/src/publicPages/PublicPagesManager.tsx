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
  const { matchedRoute, parameters } = usePageResolver();

  if (!matchedRoute) {
    return <NotFoundPage />;
  }
  const Component = matchedRoute[1];

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
        <Component routeParameters={parameters} />
      </Suspense>
    </ErrorBoundary>
  );
};

export default PublicPagesManager;
