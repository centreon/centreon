import { lazy, Suspense } from 'react';

import { Routes, Route } from 'react-router-dom';
import { flatten, isNil, not } from 'ramda';
import { useAtomValue } from 'jotai';

import { styled } from '@mui/material';

import { PageSkeleton, useMemoComponent } from '@centreon/ui';

import internalPagesRoutes from '../../reactRoutes';
import BreadcrumbTrail from '../../BreadcrumbTrail';
import useNavigation from '../../Navigation/useNavigation';
import { federatedModulesAtom } from '../../federatedModules/atoms';
import { FederatedModule } from '../../federatedModules/models';
import { Remote } from '../../federatedModules/Load';
import routeMap from '../../reactRoutes/routeMap';

const NotAllowedPage = lazy(() => import('../../FallbackPages/NotAllowedPage'));
const NotFoundPage = lazy(() => import('../../FallbackPages/NotFoundPage'));

const PageContainer = styled('div')(() => ({
  display: 'grid',
  gridTemplateRows: 'auto 1fr',
  height: '100%',
  overflow: 'auto'
}));

interface IsAllowedPageProps {
  allowedPages: Array<string | Array<string>>;
  path: string;
}

const isAllowedPage = ({ path, allowedPages }: IsAllowedPageProps): boolean =>
  flatten(allowedPages).some((allowedPage) => path?.includes(allowedPage));

const getExternalPageRoutes = ({
  allowedPages,
  federatedModules
}): Array<JSX.Element> => {
  return federatedModules?.map(
    ({ federatedPages, remoteEntry, moduleFederationName, moduleName }) => {
      return federatedPages?.map(({ component, route }) => {
        if (not(isAllowedPage({ allowedPages, path: route }))) {
          return null;
        }

        return (
          <Route
            element={
              <PageContainer>
                <BreadcrumbTrail path={route} />
                <Remote
                  component={component}
                  key={component}
                  moduleFederationName={moduleFederationName}
                  moduleName={moduleName}
                  remoteEntry={remoteEntry}
                />
              </PageContainer>
            }
            key={route}
            path={route}
          />
        );
      });
    }
  );
};

interface Props {
  allowedPages?: Array<string | Array<string>>;
  externalPagesFetched: boolean;
  federatedModules: Array<FederatedModule>;
}

const ReactRouterContent = ({
  federatedModules,
  externalPagesFetched,
  allowedPages
}: Props): JSX.Element => {
  return useMemoComponent({
    Component: (
      <Suspense fallback={<PageSkeleton />}>
        <Routes>
          {internalPagesRoutes.map(({ path, comp: Comp, ...rest }) => {
            const isLogoutPage = path === routeMap.logout;
            const isAllowed =
              isLogoutPage ||
              isNil(allowedPages) ||
              isAllowedPage({ allowedPages, path });

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
          {getExternalPageRoutes({ allowedPages, federatedModules })}
          {externalPagesFetched && (
            <Route element={<NotFoundPage />} path="*" />
          )}
        </Routes>
      </Suspense>
    ),
    memoProps: [externalPagesFetched, federatedModules, allowedPages]
  });
};

const ReactRouter = (): JSX.Element => {
  const federatedModules = useAtomValue(federatedModulesAtom);
  const { allowedPages } = useNavigation();

  const externalPagesFetched = not(isNil(federatedModules));

  return (
    <ReactRouterContent
      allowedPages={allowedPages}
      externalPagesFetched={externalPagesFetched}
      federatedModules={federatedModules as Array<FederatedModule>}
    />
  );
};

export default ReactRouter;
