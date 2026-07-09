import PollerIcon from '@mui/icons-material/DeviceHub';

import { MenuSkeleton, TopCounterLayout } from '@centreon/ui';

import { flatten, includes } from 'ramda';
import { ReactElement } from 'react';

import useNavigation from '../../Navigation/useNavigation';
import PollerStatusIcon from './PollerStatusIcon';
import { PollerSubMenu } from './PollerSubMenu/PollerSubMenu';
import { usePollerData } from './usePollerData';

export const pollerConfigurationPageNumber = '60901';

const ServiceStatusCounter = (): ReactElement | null => {
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
      renderIndicators={(): ReactElement => (
        <PollerStatusIcon iconSeverities={data.iconSeverities} />
      )}
      renderSubMenu={({ closeSubMenu }): ReactElement => (
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
