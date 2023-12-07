import { useMemo } from 'react';

import { concat, equals, filter, flatten, isNil, pluck } from 'ramda';
import { useAtomValue } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import {
  federatedModulesAtom,
  federatedWidgetsAtom
} from '../../federatedModules/atoms';
import { Remote } from '../../federatedModules/Load';
import {
  FederatedComponentsConfiguration,
  FederatedModule,
  StyleMenuSkeleton
} from '../../federatedModules/models';

interface Props extends Record<string, unknown> {
  federatedModulesConfigurations: Array<FederatedModule>;
  isFederatedWidget?: boolean;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

const getFederatedComponents = (
  federatedComponentsConfiguration: Array<FederatedComponentsConfiguration>
): Array<string> => {
  return flatten(
    pluck('federatedComponents', federatedComponentsConfiguration)
  );
};

const FederatedModules = ({
  federatedModulesConfigurations,
  styleMenuSkeleton,
  isFederatedWidget,
  ...rest
}: Props): JSX.Element | null => {
  return useMemoComponent({
    Component: (
      <>
        {federatedModulesConfigurations.map(
          ({
            remoteEntry,
            moduleFederationName,
            federatedComponentsConfiguration,
            moduleName
          }) => {
            return getFederatedComponents(federatedComponentsConfiguration).map(
              (component) => {
                return (
                  <Remote
                    isFederatedComponent
                    component={component}
                    isFederatedWidget={isFederatedWidget}
                    key={component}
                    moduleFederationName={moduleFederationName}
                    moduleName={moduleName}
                    remoteEntry={remoteEntry}
                    styleMenuSkeleton={styleMenuSkeleton}
                    {...rest}
                  />
                );
              }
            );
          }
        )}
      </>
    ),
    memoProps: [federatedModulesConfigurations, rest]
  });
};

interface LoadableComponentsContainerProps extends Record<string, unknown> {
  [props: string]: unknown;
  isFederatedWidget?: boolean;
  path: string;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

interface LoadableComponentsProps extends LoadableComponentsContainerProps {
  federatedModules: Array<FederatedModule> | null;
}

const getLoadableComponents = ({
  path,
  federatedModules
}: LoadableComponentsProps): Array<FederatedModule> | null => {
  if (isNil(federatedModules)) {
    return null;
  }

  const components = path
    ? filter(
        ({ federatedComponentsConfiguration }) =>
          federatedComponentsConfiguration.some(({ path: federatedPath }) =>
            equals(path, federatedPath)
          ),
        federatedModules
      )
    : federatedModules;

  return path
    ? components.map(({ federatedComponentsConfiguration, ...rest }) => ({
        ...rest,
        federatedComponentsConfiguration:
          federatedComponentsConfiguration.filter(({ path: federatedPath }) =>
            equals(path, federatedPath)
          )
      }))
    : components;
};

const defaultStyleMenuSkeleton = {
  className: undefined,
  height: undefined,
  width: undefined
};

const LoadableComponentsContainer = ({
  path,
  styleMenuSkeleton = defaultStyleMenuSkeleton,
  isFederatedWidget,
  ...props
}: LoadableComponentsContainerProps): JSX.Element | null => {
  const federatedModules = useAtomValue(federatedModulesAtom);
  const federatedWidgets = useAtomValue(federatedWidgetsAtom);

  const federatedModulesToDisplay = useMemo(
    () =>
      getLoadableComponents({
        federatedModules: concat(
          federatedModules || [],
          federatedWidgets || []
        ),
        path
      }),
    [federatedModules, path, federatedWidgets]
  );

  if (isNil(federatedModulesToDisplay)) {
    return null;
  }

  return (
    <FederatedModules
      federatedModulesConfigurations={federatedModulesToDisplay}
      isFederatedWidget={isFederatedWidget}
      styleMenuSkeleton={styleMenuSkeleton}
      {...props}
    />
  );
};

export default LoadableComponentsContainer;
