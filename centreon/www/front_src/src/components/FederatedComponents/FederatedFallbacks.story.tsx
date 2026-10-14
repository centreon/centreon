import FederatedComponentFallback from '../../federatedModules/Load/FederatedComponentFallback';
import FederatedPageFallback from '../../federatedModules/Load/FederatedPageFallback';

export const PageFallback = (): JSX.Element => <FederatedPageFallback />;

export const ComponentFallback = (): JSX.Element => (
  <FederatedComponentFallback />
);
