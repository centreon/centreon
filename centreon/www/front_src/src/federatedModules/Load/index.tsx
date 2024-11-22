import { ReactNode, Suspense, lazy, useMemo } from 'react';

import { importRemote } from '@module-federation/utilities';
import { isEmpty, isNil } from 'ramda';

import { MenuSkeleton, PageSkeleton } from '@centreon/ui';

import { store } from '../../Main/Provider';
import { StyleMenuSkeleton } from '../models';

import ErrorBoundary from './ErrorBoundary';
import FederatedComponentFallback from './FederatedComponentFallback';
import FederatedPageFallback from './FederatedPageFallback';

interface RemoteProps {
  children?: ReactNode;
  component: string;
  isFederatedComponent?: boolean;
  isFederatedWidget?: boolean;
  moduleFederationName: string;
  moduleName: string;
  remoteEntry: string;
  remoteUrl?: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

export const Remote = ({
  component,
  remoteEntry,
  remoteUrl,
  moduleName,
  moduleFederationName,
  isFederatedComponent,
  isFederatedWidget,
  styleMenuSkeleton,
  children,
  ...props
}: RemoteProps): JSX.Element => {
  const prefix = isFederatedWidget ? 'widgets' : 'modules';

  const Component = useMemo(
    () =>
      lazy(() =>
        importRemote({
          bustRemoteEntryCache: false,
          module: component,
          remoteEntryFileName: remoteEntry,
          scope: moduleFederationName,
          url: remoteUrl ?? `./${prefix}/${moduleName}/static`
        })
      ),
    [component, moduleName, remoteEntry, moduleFederationName, remoteUrl]
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
          {children ? (
            <Component {...props} store={store}>
              {children}
            </Component>
          ) : (
            <Component {...props} store={store} />
          )}
        </Suspense>
      </ErrorBoundary>
    );
  }

  return (
    <ErrorBoundary fallback={fallback}>
      <Suspense
        fallback={isFederatedComponent ? <MenuSkeleton /> : <PageSkeleton />}
      >
        {children ? (
          <Component store={store} {...props}>
            {children}
          </Component>
        ) : (
          <Component store={store} {...props} />
        )}
      </Suspense>
    </ErrorBoundary>
  );
};
