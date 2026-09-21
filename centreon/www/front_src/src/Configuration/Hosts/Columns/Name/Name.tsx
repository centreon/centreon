import {
  type ComponentColumnProps,
  HostIcon,
  Image,
  truncate
} from '@centreon/ui';

import type { JSX } from 'react';

import type { Icon } from '../../models';
import { useNameStyles } from './Name.styles';

const Name = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const { classes } = useNameStyles();

  const icon = row.icon as Icon | undefined | null;

  const name = renderEllipsisTypography?.({
    formattedString: truncate({ content: row.name as string, maxLength: 50 })
  });

  return (
    <div className={classes.container}>
      {/* `Image` renders nothing when `imagePath` is nil: it never falls back.
          The explicit branch is what shows the default icon. */}
      {icon?.url ? (
        <Image
          // `useLoadImage` caches by `alt`, so it must identify the icon, not the row.
          alt={icon.name}
          className={classes.icon}
          fallback={<HostIcon className={classes.icon} />}
          imagePath={icon.url}
        />
      ) : (
        <HostIcon className={classes.icon} />
      )}
      {name}
    </div>
  );
};

export default Name;
