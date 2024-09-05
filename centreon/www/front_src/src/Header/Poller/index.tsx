import { flatten, includes } from 'ramda';

import PollerIcon from '@mui/icons-material/DeviceHub';

import { MenuSkeleton, TopCounterLayout } from '@centreon/ui';

import useNavigation from '../../Navigation/useNavigation';

import PollerStatusIcon from './PollerStatusIcon';
import { PollerSubMenu } from './PollerSubMenu/PollerSubMenu';
import { usePollerData } from './usePollerData';

export const pollerConfigurationPageNumber = '60901';

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = usePollerData();
  const { allowedPages } = useNavigation();

  const displayPollerButton =
    !!allowedPages &&
    includes(pollerConfigurationPageNumber, flatten(allowedPages));

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <TopCounterLayout
      Icon={PollerIcon}
      renderIndicators={(): JSX.Element => (
        <PollerStatusIcon iconSeverities={data.iconSeverities} />
      )}
      renderSubMenu={({ closeSubMenu }): JSX.Element => (
        <PollerSubMenu
          {...data.subMenu}
          closeSubMenu={closeSubMenu}
          displayPollerButton={displayPollerButton}
        />
      )}
      title={data.buttonLabel}
    />
  );
};

export default ServiceStatusCounter;
