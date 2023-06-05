import { LoadingSkeleton, Image } from '@centreon/ui';

import Icon from './bell.svg';

const EmptyNotificationsIcon = (): JSX.Element => {
  return (
    <Image
      alt="No notification foud!"
      fallback={<LoadingSkeleton />}
      height={180}
      imagePath={Icon}
      width={230}
    />
  );
};

export default EmptyNotificationsIcon;
