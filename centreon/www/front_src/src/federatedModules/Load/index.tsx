import { lazy, Suspense, useMemo } from 'react';

import { importRemote } from '@module-federation/utilities';
import { equals, isEmpty, isNil } from 'ramda';

import { MenuSkeleton, PageSkeleton } from '@centreon/ui';

import { StyleMenuSkeleton } from '../models';
import { store } from '../../Main/Provider';

import ErrorBoundary from './ErrorBoundary';
import FederatedComponentFallback from './FederatedComponentFallback';
import FederatedPageFallback from './FederatedPageFallback';

interface RemoteProps {
  component: string;
  isFederatedComponent?: boolean;
  isFederatedWidget?: boolean;
  moduleFederationName: string;
  moduleName: string;
  remoteEntry: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

export const Remote = ({
  component,
  remoteEntry,
  moduleName,
  moduleFederationName,
  isFederatedComponent,
  isFederatedWidget,
  styleMenuSkeleton,
  ...props
}: RemoteProps): JSX.Element => {
  const prefix = isFederatedWidget ? 'widgets' : 'modules';

  const Component = useMemo(
    () =>
      lazy(() =>
        equals(window.Cypress?.testingType, 'component')
          ? import(`www/widgets/src/${moduleFederationName}`)
          : importRemote({
              bustRemoteEntryCache: false,
              module: component,
              remoteEntryFileName: remoteEntry,
              scope: moduleFederationName,
              url: `./${prefix}/${moduleName}/static`
            })
      ),
    [component, moduleName, remoteEntry, moduleFederationName]
  );

  const fallback = isFederatedComponent ? (
    <FederatedComponentFallback />
  ) : (
    <FederatedPageFallback />
  );

  if (!isNil(styleMenuSkeleton) && !isEmpty(styleMenuSkeleton)) {
    const { height, width, className } = styleMenuSkeleton;

    return (
      <ErrorBoundary fallback={fallback}>
        <Suspense
          fallback={
            isFederatedComponent ? (
              <MenuSkeleton
                className={className}
                height={height}
                width={width}
              />
            ) : (
              <PageSkeleton />
            )
          }
        >
          <Component {...props} store={store} />
        </Suspense>
      </ErrorBoundary>
    );
  }

  return (
    <ErrorBoundary fallback={fallback}>
      <Suspense
        fallback={isFederatedComponent ? <MenuSkeleton /> : <PageSkeleton />}
      >
        <Component {...props} store={store} />
      </Suspense>
    </ErrorBoundary>
  );
};
