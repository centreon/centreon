import { LoadingSkeleton, Image } from '@centreon/ui';

import Icon from './bell.svg';

const EmptyNotificationsIcon = (): JSX.Element => {
  return (
    <Image
      alt="No notification foud!"
      fallback={<LoadingSkeleton />}
      height={279}
      imagePath={Icon}
      width={328}
    />
  );
};

export default EmptyNotificationsIcon;
