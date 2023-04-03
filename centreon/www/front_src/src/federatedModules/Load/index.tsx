import { lazy, memo, Suspense, useEffect, useMemo, useState } from 'react';

import { atom, useAtom } from 'jotai';
import { equals, isEmpty, isNil, omit, pick } from 'ramda';

import { MenuSkeleton, PageSkeleton } from '@centreon/ui';

import NotFoundPage from '../../FallbackPages/NotFoundPage';
import { StyleMenuSkeleton } from '../models';

import loadComponent from './loadComponent';

interface UseDynamicLoadRemoteEntryState {
  failed: boolean;
  ready: boolean;
}

interface UseDynamicLoadRemoteEntryProps {
  isFederatedWidget?: boolean;
  moduleName: string;
  remoteEntry: string;
}

const remoteEntriesLoadedAtom = atom([] as Array<string>);

const useDynamicLoadRemoteEntry = ({
  remoteEntry,
  moduleName,
  isFederatedWidget
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

    const prefix = isFederatedWidget ? 'widgets' : 'modules';

    const element = document.createElement('script');
    element.src = `./${prefix}/${moduleName}/static/${remoteEntry}`;
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
  memoProps: Array<unknown>;
  moduleFederationName: string;
  name: string;
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

  const componentProps = omit(['name', 'memoProps'], props);

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
        <Component {...componentProps} />
      </Suspense>
    );
  }

  return (
    <Suspense
      fallback={isFederatedModule ? <MenuSkeleton /> : <PageSkeleton />}
    >
      <Component {...componentProps} />
    </Suspense>
  );
};

const memoizeProps = pick(['name', 'component', 'isFederated', 'memoProps']);
const MemoizedLoadComponent = memo(LoadComponent, (prevProps, nextProps) =>
  equals(memoizeProps(prevProps), memoizeProps(nextProps))
);

interface RemoteProps extends LoadComponentProps {
  isFederatedWidget?: boolean;
  memoProps: Array<unknown>;
  moduleName: string;
  remoteEntry: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

export const Remote = ({
  isFederatedWidget,
  memoProps,
  component,
  remoteEntry,
  moduleName,
  moduleFederationName,
  isFederatedModule,
  styleMenuSkeleton,
  ...props
}: RemoteProps): JSX.Element => {
  const { ready, failed } = useDynamicLoadRemoteEntry({
    isFederatedWidget,
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
    <MemoizedLoadComponent
      {...props}
      component={component}
      isFederatedModule={isFederatedModule}
      memoProps={memoProps}
      moduleFederationName={moduleFederationName}
      name={moduleName}
      styleMenuSkeleton={styleMenuSkeleton}
    />
  );
};
