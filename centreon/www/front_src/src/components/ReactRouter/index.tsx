import { Suspense, lazy } from 'react';

import { animated, useTransition } from '@react-spring/web';
import { QueryClientProvider } from '@tanstack/react-query';
import { useAtomValue } from 'jotai';
import { flatten, isNil, not } from 'ramda';
import { Route, Routes, useLocation, useParams } from 'react-router-dom';

import { styled } from '@mui/material';

import { PageSkeleton, client, useMemoComponent } from '@centreon/ui';
import {
  featureFlagsDerivedAtom,
  federatedModulesAtom
} from '@centreon/ui-context';

import BreadcrumbTrail from '../../BreadcrumbTrail';
import useNavigation from '../../Navigation/useNavigation';
import { Remote } from '../../federatedModules/Load';
import { childrenComponentsMapping } from '../../federatedModules/childrenComponentsMapping';
import { FederatedModule } from '../../federatedModules/models';
import internalPagesRoutes from '../../reactRoutes';
import { deprecatedRoutes } from '../../reactRoutes/deprecatedRoutes';
import routeMap from '../../reactRoutes/routeMap';

import DeprecatedRoute from './DeprecatedRoute';

const NotAllowedPage = lazy(() => import('../../FallbackPages/NotAllowedPage'));
const NotFoundPage = lazy(() => import('../../FallbackPages/NotFoundPage'));

const PageContainer = styled('div')(() => ({
  display: 'grid',
  gridTemplateRows: 'auto 1fr',
  height: '100%',
  overflow: 'auto'
}));

export const pageTransitionConfig = {
  config: {
    duration: 150
  },
  enter: {
    height: '100%',
    opacity: '1',
    width: '100%'
  },
  from: {
    height: '100%',
    opacity: '0',
    width: '100%'
  },
  leave: {
    height: '100%',
    opacity: '0',
    width: '100%'
  }
};

interface IsAllowedPageProps {
  allowedPages?: Array<string | Array<string>>;
  path?: string;
}

interface GetExternalPageRoutesProps {
  allowedPages?: Array<string | Array<string>>;
  featureFlags: Record<string, boolean>;
  federatedModules: Array<FederatedModule>;
}

const isAllowedPage = ({ path, allowedPages }: IsAllowedPageProps): boolean =>
  flatten(allowedPages || []).some((allowedPage) =>
    path?.includes(allowedPage)
  );

const getExternalPageRoutes = ({
  allowedPages,
  federatedModules,
  featureFlags
}: GetExternalPageRoutesProps): Array<Array<JSX.Element | null>> => {
  return federatedModules?.map(
    ({
      federatedPages,
      remoteEntry,
      moduleFederationName,
      moduleName,
      remoteUrl
    }) => {
      const filteredPagesByFeatureFlag = (federatedPages || [])?.filter(
        ({ featureFlag }) => {
          if (isNil(featureFlag)) {
            return true;
          }

          return featureFlags[featureFlag];
        }
      );

      return filteredPagesByFeatureFlag.map(
        ({ component, route, children }) => {
          if (not(isAllowedPage({ allowedPages, path: route }))) {
            return null;
          }

          const ChildrenComponent: ((props) => JSX.Element) | null | undefined =
            children ? childrenComponentsMapping[children] : undefined;

          return (
            <Route
              element={
                <PageContainer>
                  <BreadcrumbTrail path={route} />
                  {ChildrenComponent ? (
                    <Remote
                      component={component}
                      key={component}
                      moduleFederationName={moduleFederationName}
                      moduleName={moduleName}
                      remoteEntry={remoteEntry}
                      remoteUrl={remoteUrl}
                    >
                      {(props): JSX.Element => <ChildrenComponent {...props} />}
                    </Remote>
                  ) : (
                    <Remote
                      component={component}
                      key={component}
                      moduleFederationName={moduleFederationName}
                      moduleName={moduleName}
                      remoteEntry={remoteEntry}
                      remoteUrl={remoteUrl}
                    />
                  )}
                </PageContainer>
              }
              key={route}
              path={route}
            />
          );
        }
      );
    }
  );
};

interface Props {
  allowedPages?: Array<string | Array<string>>;
  externalPagesFetched: boolean;
  federatedModules: Array<FederatedModule>;
  pathname: string;
}

const ReactRouterContent = ({
  federatedModules,
  externalPagesFetched,
  allowedPages,
  pathname
}: Props): JSX.Element => {
  const parameters = useParams();

  const featureFlags = useAtomValue(featureFlagsDerivedAtom);

  return useMemoComponent({
    Component: (
      <Suspense fallback={<PageSkeleton />}>
        <QueryClientProvider client={client}>
          <Routes location={pathname}>
            {...deprecatedRoutes
              .filter((route) => !route.ignoreWhen?.(pathname))
              .map(({ deprecatedRoute, newRoute }) => (
                <Route
                  element={<DeprecatedRoute newRoute={newRoute} />}
                  key={deprecatedRoute.path}
                  path={deprecatedRoute.path}
                />
              ))}
            {internalPagesRoutes.map(({ path, comp: Comp, ...rest }) => {
              const isLogoutPage = path === routeMap.logout;
              const isAllowed =
                isLogoutPage || isAllowedPage({ allowedPages, path });

              return (
                <Route
                  element={
                    isAllowed ? (
                      <PageContainer>
                        <BreadcrumbTrail path={path} />
                        <Comp />
                      </PageContainer>
                    ) : (
                      <NotAllowedPage />
                    )
                  }
                  key={path}
                  path={path}
                  {...rest}
                />
              );
            })}
            {getExternalPageRoutes({
              allowedPages,
              featureFlags,
              federatedModules
            })}
            {externalPagesFetched && (
              <Route element={<NotFoundPage />} path="*" />
            )}
          </Routes>
        </QueryClientProvider>
      </Suspense>
    ),
    memoProps: [
      externalPagesFetched,
      federatedModules,
      allowedPages,
      pathname,
      parameters,
      deprecatedRoutes
    ]
  });
};

const ReactRouter = (): JSX.Element => {
  const federatedModules = useAtomValue(federatedModulesAtom);
  const { allowedPages } = useNavigation();
  const { pathname } = useLocation();

  const transitions = useTransition(pathname, pageTransitionConfig);

  if (isNil(allowedPages)) {
    return <PageSkeleton />;
  }

  const externalPagesFetched = not(isNil(federatedModules));

  return transitions((style, item) => (
    <animated.div style={style}>
      <ReactRouterContent
        allowedPages={allowedPages}
        externalPagesFetched={externalPagesFetched}
        federatedModules={federatedModules as Array<FederatedModule>}
        pathname={item}
      />
    </animated.div>
  ));
};

export default ReactRouter;
