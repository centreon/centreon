import { useAtomValue } from 'jotai';
import { equals, reject, type } from 'ramda';

import { federatedModulesAtom, isOnPublicPageAtom } from '@centreon/ui-context';

import { Remote } from '../../federatedModules/Load';
import FederatedPageFallback from '../../federatedModules/Load/FederatedPageFallback';
import { childrenComponentsMapping } from '../../federatedModules/childrenComponentsMapping';
import { FederatedModule, PageComponent } from '../../federatedModules/models';
import { PageSkeleton } from '@centreon/ui';

interface Props {
  childrenComponent?: string;
  route: string;
}

const FederatedPage = ({
  route,
  childrenComponent,
  ...rest
}: Props): JSX.Element => {
  const federatedModules = useAtomValue(federatedModulesAtom);
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  if(!federatedModules){
    return <PageSkeleton />
  }

  const filteredFederatedModules = reject(
    (federatedModule) => equals(type(federatedModule), 'String'),
    federatedModules || []
  );

  const module = filteredFederatedModules?.find(({ federatedPages }) =>
    federatedPages?.find((page) => equals(page.route, route))
  ) as FederatedModule | undefined;

  if (!module) {
    return <FederatedPageFallback />;
  }

  const ChildrenComponent: ((props) => JSX.Element) | null | undefined =
    childrenComponent
      ? childrenComponentsMapping[childrenComponent]
      : undefined;

  const pageConfiguration = module.federatedPages.find((page) =>
    equals(page.route, route)
  ) as PageComponent;

  const additionalProps = {
    isOnPublicPage,
    ...rest
  };

  return ChildrenComponent ? (
    <Remote
      component={pageConfiguration.component}
      key={pageConfiguration.component}
      moduleFederationName={module.moduleFederationName}
      moduleName={module.moduleName}
      remoteEntry={module.remoteEntry}
      remoteUrl={module.remoteUrl}
      {...additionalProps}
    >
      {(props): JSX.Element => <ChildrenComponent {...props} />}
    </Remote>
  ) : (
    <Remote
      component={pageConfiguration.component}
      key={pageConfiguration.component}
      moduleFederationName={module.moduleFederationName}
      moduleName={module.moduleName}
      remoteEntry={module.remoteEntry}
      remoteUrl={module.remoteUrl}
      {...additionalProps}
    />
  );
};

export default FederatedPage;
