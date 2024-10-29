import { ReactNode, useMemo } from 'react';

import { useAtomValue } from 'jotai';
import {
  concat,
  equals,
  filter,
  flatten,
  isNil,
  pluck,
  reject,
  type
} from 'ramda';

import { useMemoComponent } from '@centreon/ui';
import {
  federatedModulesAtom,
  federatedWidgetsAtom
} from '@centreon/ui-context';

import { Remote } from '../../federatedModules/Load';
import {
  FederatedComponentsConfiguration,
  FederatedModule,
  StyleMenuSkeleton
} from '../../federatedModules/models';

interface Props extends Record<string, unknown> {
  children?: ReactNode;
  federatedModulesConfigurations: Array<FederatedModule>;
  isFederatedWidget?: boolean;
  styleMenuSkeleton?: StyleMenuSkeleton;
}

const getFederatedComponents = (
  federatedComponentsConfiguration: Array<FederatedComponentsConfiguration>
): Array<string> => {
  if (equals(type(federatedComponentsConfiguration), 'Object')) {
    return federatedComponentsConfiguration.federatedComponents;
  }

  return flatten(
    pluck('federatedComponents', federatedComponentsConfiguration)
  );
};

const FederatedModules = ({
  federatedModulesConfigurations,
  styleMenuSkeleton,
  isFederatedWidget,
  children,
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
                  >
                    {children}
                  </Remote>
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
  children?: ReactNode;
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

  const filteredFederatedModules = reject(
    (federatedModule) => equals(type(federatedModule), 'String'),
    federatedModules
  );

  const components = path
    ? filter(({ federatedComponentsConfiguration }) => {
        if (equals(type(federatedComponentsConfiguration), 'Object')) {
          return equals(path, federatedComponentsConfiguration.path);
        }

        return federatedComponentsConfiguration?.some(
          ({ path: federatedPath }) => equals(path, federatedPath)
        );
      }, filteredFederatedModules)
    : filteredFederatedModules;

  return path
    ? components.map(({ federatedComponentsConfiguration, ...rest }) => ({
        ...rest,
        federatedComponentsConfiguration: equals(
          type(federatedComponentsConfiguration),
          'Object'
        )
          ? federatedComponentsConfiguration
          : federatedComponentsConfiguration?.filter(
              ({ path: federatedPath }) => equals(path, federatedPath)
            )
      }))
    : components;
};

const LoadableComponentsContainer = ({
  path,
  styleMenuSkeleton,
  isFederatedWidget,
  children,
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
    >
      {children}
    </FederatedModules>
  );
};

export default LoadableComponentsContainer;
