<<<<<<< HEAD
import { lazy, Suspense } from 'react';

import { Routes, Route, useHref } from 'react-router-dom';
import { isNil, not, propOr } from 'ramda';
import { useAtomValue } from 'jotai/utils';

import { styled } from '@mui/material';

import { PageSkeleton, useMemoComponent } from '@centreon/ui';

import internalPagesRoutes from '../../reactRoutes';
import { dynamicImport } from '../../helpers/dynamicImport';
import BreadcrumbTrail from '../../BreadcrumbTrail';
import useNavigation from '../../Navigation/useNavigation';
import { externalComponentsAtom } from '../../externalComponents/atoms';
import ExternalComponents, {
  ExternalComponent,
} from '../../externalComponents/models';

const NotAllowedPage = lazy(() => import('../../FallbackPages/NotAllowedPage'));
const NotFoundPage = lazy(() => import('../../FallbackPages/NotFoundPage'));
=======
import * as React from 'react';

import { connect } from 'react-redux';
import { Switch, Route, withRouter } from 'react-router-dom';
import { equals } from 'ramda';

import { styled } from '@material-ui/core';

import { PageSkeleton } from '@centreon/ui';

import internalPagesRoutes from '../../route-maps';
import { dynamicImport } from '../../helpers/dynamicImport';
import NotAllowedPage from '../../route-components/notAllowedPage';
import BreadcrumbTrail from '../../BreadcrumbTrail';
import { allowedPagesSelector } from '../../redux/selectors/navigation/allowedPages';
>>>>>>> centreon/dev-21.10.x

const PageContainer = styled('div')(({ theme }) => ({
  background: theme.palette.background.default,
  display: 'grid',
  gridTemplateRows: 'auto 1fr',
  height: '100%',
  overflow: 'auto',
}));

const getExternalPageRoutes = ({
<<<<<<< HEAD
  allowedPages,
  pages,
  basename,
}): Array<JSX.Element> => {
=======
  history,
  allowedPages,
  pages,
}): Array<JSX.Element> => {
  const basename = history.createHref({
    hash: '',
    pathname: '/',
    search: '',
  });

>>>>>>> centreon/dev-21.10.x
  const pageEntries = Object.entries(pages);
  const isAllowedPage = (path): boolean =>
    allowedPages?.find((allowedPage) => path.includes(allowedPage));

  const loadablePages = pageEntries.filter(([path]) => isAllowedPage(path));

  return loadablePages.map(([path, parameter]) => {
<<<<<<< HEAD
    const Page = lazy(() => dynamicImport(basename, parameter));

    return (
      <Route
        element={
          <PageContainer>
            <BreadcrumbTrail path={path} />
            <Suspense
              fallback={<PageSkeleton displayHeaderAndNavigation={false} />}
            >
              <Page />
            </Suspense>
          </PageContainer>
        }
        key={path}
        path={path}
=======
    const Page = React.lazy(() => dynamicImport(basename, parameter));

    return (
      <Route
        exact
        key={path}
        path={path}
        render={(renderProps): JSX.Element => (
          <PageContainer>
            <BreadcrumbTrail path={path} />
            <Page {...renderProps} />
          </PageContainer>
        )}
>>>>>>> centreon/dev-21.10.x
      />
    );
  });
};

interface Props {
<<<<<<< HEAD
  allowedPages: Array<string | Array<string>>;
  externalPagesFetched: boolean;
  pages: Record<string, unknown>;
}

const ReactRouterContent = ({
  pages,
  externalPagesFetched,
  allowedPages,
}: Props): JSX.Element => {
  const basename = useHref('/');

  return useMemoComponent({
    Component: (
      <Suspense fallback={<PageSkeleton />}>
        <Routes>
          {internalPagesRoutes.map(({ path, comp: Comp, ...rest }) => (
            <Route
              element={
                allowedPages.includes(path) ? (
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
          ))}
          {getExternalPageRoutes({ allowedPages, basename, pages })}
          {externalPagesFetched && (
            <Route element={<NotFoundPage />} path="*" />
          )}
        </Routes>
      </Suspense>
    ),
    memoProps: [externalPagesFetched, pages, allowedPages],
  });
};

const ReactRouter = (): JSX.Element => {
  const externalComponents = useAtomValue(externalComponentsAtom);
  const { allowedPages } = useNavigation();

  const externalPagesFetched = not(isNil(externalComponents));

  if (!externalPagesFetched || !allowedPages) {
    return <PageSkeleton />;
  }

  const pages = propOr<undefined, ExternalComponents | null, ExternalComponent>(
    undefined,
    'pages',
    externalComponents,
  );

  return (
    <ReactRouterContent
      allowedPages={allowedPages}
      externalPagesFetched={externalPagesFetched}
      pages={pages}
    />
  );
};

export default ReactRouter;
=======
  allowedPages: Array<string>;
  externalPagesFetched: boolean;
  history;
  pages: Record<string, unknown>;
}

const ReactRouter = React.memo<Props>(
  ({ allowedPages, history, pages, externalPagesFetched }: Props) => {
    if (!externalPagesFetched || !allowedPages) {
      return <PageSkeleton />;
    }

    return (
      <React.Suspense fallback={<PageSkeleton />}>
        <Switch>
          {internalPagesRoutes.map(({ path, comp: Comp, ...rest }) => (
            <Route
              exact
              key={path}
              path={path}
              render={(renderProps): JSX.Element => (
                <PageContainer>
                  {allowedPages.includes(path) ? (
                    <>
                      <BreadcrumbTrail path={path} />
                      <Comp {...renderProps} />
                    </>
                  ) : (
                    <NotAllowedPage {...renderProps} />
                  )}
                </PageContainer>
              )}
              {...rest}
            />
          ))}
          {getExternalPageRoutes({ allowedPages, history, pages })}
          {externalPagesFetched && <Route component={NotAllowedPage} />}
        </Switch>
      </React.Suspense>
    );
  },
  (previousProps, nextProps) =>
    equals(previousProps.pages, nextProps.pages) &&
    equals(previousProps.allowedPages, nextProps.allowedPages) &&
    equals(previousProps.externalPagesFetched, nextProps.externalPagesFetched),
);

const mapStateToProps = (state): Record<string, unknown> => ({
  allowedPages: allowedPagesSelector(state),
  externalPagesFetched: state.externalComponents.fetched,
  pages: state.externalComponents.pages,
});

export default connect(mapStateToProps)(withRouter(ReactRouter));
>>>>>>> centreon/dev-21.10.x
