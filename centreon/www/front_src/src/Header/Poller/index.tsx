import PollerIcon from '@mui/icons-material/DeviceHub';

import { MenuSkeleton, ItemLayout } from '@centreon/ui';

import PollerStatusIcon from './PollerStatusIcon';
import { PollerSubMenu } from './PollerSubMenu/PollerSubMenu';
import { usePollerData } from './usePollerData';
import { labelPollers } from './translatedLabels';

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = usePollerData();

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  if (!isAllowed || !data) {
    return null;
  }

  return (
    <ItemLayout
      Icon={PollerIcon}
      renderIndicators={(): JSX.Element => (
        <PollerStatusIcon iconSeverities={data.iconSeverities} />
      )}
      renderSubMenu={({ closeSubMenu }): JSX.Element => (
        <PollerSubMenu {...data.subMenu} closeSubMenu={closeSubMenu} />
      )}
      title={labelPollers}
    />
  );
};

export default ServiceStatusCounter;
