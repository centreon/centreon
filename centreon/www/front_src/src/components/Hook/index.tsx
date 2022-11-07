<<<<<<< HEAD
import { lazy, Suspense } from 'react';

import { isNil, propOr } from 'ramda';
import { useHref } from 'react-router';
import { useAtomValue } from 'jotai/utils';

import { useMemoComponent, MenuSkeleton } from '@centreon/ui';

import { dynamicImport } from '../../helpers/dynamicImport';
import { externalComponentsAtom } from '../../externalComponents/atoms';
import ExternalComponents, {
  ExternalComponent,
} from '../../externalComponents/models';

interface Props {
  hooks: ExternalComponent;
  path: string;
}

const LoadableHooks = ({ hooks, path, ...rest }: Props): JSX.Element | null => {
  const basename = useHref('/');

  return useMemoComponent({
    Component: (
      <>
        {Object.entries(hooks)
          .filter(([hook]) => hook.includes(path))
          .map(([, parameters]) => {
            const HookComponent = lazy(() =>
              dynamicImport(basename, parameters),
            );

            return (
              <Suspense fallback={<MenuSkeleton width={29} />} key={path}>
                <HookComponent {...rest} />
              </Suspense>
            );
          })}
      </>
    ),
    memoProps: [hooks],
  });
};

const Hook = (props: Pick<Props, 'path'>): JSX.Element | null => {
  const externalComponents = useAtomValue(externalComponentsAtom);

  const hooks = propOr<undefined, ExternalComponents | null, ExternalComponent>(
    undefined,
    'hooks',
    externalComponents,
  );

  if (isNil(hooks)) {
    return null;
  }

  return <LoadableHooks hooks={hooks} {...props} />;
};

export default Hook;
=======
import * as React from 'react';

import { connect } from 'react-redux';
import { withRouter } from 'react-router-dom';
import { equals } from 'ramda';

import { dynamicImport } from '../../helpers/dynamicImport';
import MenuLoader from '../MenuLoader';

interface Props {
  history;
  hooks;
  path;
}

const LoadableHooks = ({
  history,
  hooks,
  path,
  ...rest
}: Props): JSX.Element => {
  const basename = history.createHref({
    hash: '',
    pathname: '/',
    search: '',
  });

  return (
    <>
      {Object.entries(hooks)
        .filter(([hook]) => hook.includes(path))
        .map(([, parameters]) => {
          const HookComponent = React.lazy(() =>
            dynamicImport(basename, parameters),
          );

          return (
            <React.Suspense fallback={<MenuLoader width={29} />} key={path}>
              <HookComponent {...rest} />
            </React.Suspense>
          );
        })}
    </>
  );
};

const Hook = React.memo(
  (props: Props) => {
    return <LoadableHooks {...props} />;
  },
  ({ hooks: previousHooks }, { hooks: nextHooks }) =>
    equals(previousHooks, nextHooks),
);

const mapStateToProps = ({ externalComponents }): Record<string, unknown> => ({
  hooks: externalComponents.hooks,
});

export default connect(mapStateToProps)(withRouter(Hook));
>>>>>>> centreon/dev-21.10.x
