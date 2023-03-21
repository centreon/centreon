import { lazy, Suspense, useEffect, useMemo, useState } from 'react';

import { atom, useAtom } from 'jotai';
import { isEmpty, isNil } from 'ramda';

import { MenuSkeleton, PageSkeleton } from '@centreon/ui';

import NotFoundPage from '../../FallbackPages/NotFoundPage';
import { StyleMenuSkeleton } from '../models';

import loadComponent from './loadComponent';

interface UseDynamicLoadRemoteEntryState {
  failed: boolean;
  ready: boolean;
}

interface UseDynamicLoadRemoteEntryProps {
  moduleName: string;
  remoteEntry: string;
}

const remoteEntriesLoadedAtom = atom([] as Array<string>);

const useDynamicLoadRemoteEntry = ({
  remoteEntry,
  moduleName
}: UseDynamicLoadRemoteEntryProps): UseDynamicLoadRemoteEntryState => {
  const [failed, setFailed] = useState(false);

  const [remoteEntriesLoaded, setRemoteEntriesLoaded] = useAtom(
    remoteEntriesLoadedAtom
  );

  useEffect((): (() => void) | undefined => {
    if (isEmpty(remoteEntry)) {
      return undefined;
    }

    const remoteEntryElement = document.getElementById(moduleName);

    if (remoteEntryElement && remoteEntriesLoaded.includes(moduleName)) {
      return undefined;
    }

    const element = document.createElement('script');
    element.src = `./modules/${moduleName}/static/${remoteEntry}`;
    element.type = 'text/javascript';
    element.id = moduleName;

    element.onload = (): void => {
      setRemoteEntriesLoaded((currentRemoteEntries) => [
        ...currentRemoteEntries,
        moduleName
      ]);
    };

    element.onerror = (): void => {
      setFailed(true);
    };

    document.head.appendChild(element);

    return (): void => {
      document.head.removeChild(element);
    };
  }, []);

  return {
    failed,
    ready: remoteEntriesLoaded.includes(moduleName)
  };
};

interface LoadComponentProps {
  component: string;
  isFederatedModule?: boolean;
  moduleFederationName: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

const LoadComponent = ({
  moduleFederationName,
  component,
  isFederatedModule,
  styleMenuSkeleton,
  ...props
}: LoadComponentProps): JSX.Element => {
  const Component = useMemo(
    () => lazy(loadComponent({ component, moduleFederationName })),
    [moduleFederationName]
  );
  if (!isNil(styleMenuSkeleton) && !isEmpty(styleMenuSkeleton)) {
    const { height, width, className } = styleMenuSkeleton;

    return (
      <Suspense
        fallback={
          isFederatedModule ? (
            <MenuSkeleton className={className} height={height} width={width} />
          ) : (
            <PageSkeleton />
          )
        }
      >
        <Component {...props} />
      </Suspense>
    );
  }

  return (
    <Suspense
      fallback={isFederatedModule ? <MenuSkeleton /> : <PageSkeleton />}
    >
      <Component {...props} />
    </Suspense>
  );
};

interface RemoteProps extends LoadComponentProps {
  moduleName: string;
  remoteEntry: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

export const Remote = ({
  component,
  remoteEntry,
  moduleName,
  moduleFederationName,
  isFederatedModule,
  styleMenuSkeleton,
  ...props
}: RemoteProps): JSX.Element => {
  const { ready, failed } = useDynamicLoadRemoteEntry({
    moduleName,
    remoteEntry
  });

  if (!ready) {
    if (!isNil(styleMenuSkeleton) && !isEmpty(styleMenuSkeleton)) {
      const { height, width, className } = styleMenuSkeleton;

      return isFederatedModule ? (
        <MenuSkeleton className={className} height={height} width={width} />
      ) : (
        <PageSkeleton />
      );
    }

    return isFederatedModule ? <MenuSkeleton /> : <PageSkeleton />;
  }

  if (failed) {
    return <NotFoundPage />;
  }

  return (
    <LoadComponent
      component={component}
      isFederatedModule={isFederatedModule}
      moduleFederationName={moduleFederationName}
      styleMenuSkeleton={styleMenuSkeleton}
      {...props}
    />
  );
};
