import { withTranslation } from 'react-i18next';

import PollerIcon from '@mui/icons-material/DeviceHub';

import { MenuSkeleton } from '@centreon/ui';

import ItemLayout from '../sharedUI/ItemLayout';

import PollerStatusIcon from './PollerStatusIcon';
import { PollerSubMenu } from './PollerSubMenu/PollerSubMenu';
import { usePollerData } from './usePollerData';

const ServiceStatusCounter = (): JSX.Element | null => {
  const { isLoading, data, isAllowed } = usePollerData();

  if (!isAllowed) {
    return null;
  }

  if (isLoading) {
    return <MenuSkeleton width={20} />;
  }

  return (
    data && (
      <ItemLayout
        Icon={PollerIcon}
        renderIndicators={(): JSX.Element => (
          <PollerStatusIcon iconSeverities={data.iconSeverities} />
        )}
        renderSubMenu={({ closeSubMenu }): JSX.Element => (
          <PollerSubMenu {...data.subMenu} closeSubMenu={closeSubMenu} />
        )}
        testId="Pollers"
        title="Pollers"
      />
    )
  );
};

export default withTranslation()(ServiceStatusCounter);
