import PollerIcon from '@mui/icons-material/DeviceHub';

import { MenuSkeleton, TopCounterLayout } from '@centreon/ui';

import PollerStatusIcon from './PollerStatusIcon';
import { PollerSubMenu } from './PollerSubMenu/PollerSubMenu';
import { usePollerData } from './usePollerData';

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = usePollerData();

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
        <PollerSubMenu {...data.subMenu} closeSubMenu={closeSubMenu} />
      )}
      title={data.buttonLabel}
    />
  );
};

export default ServiceStatusCounter;
