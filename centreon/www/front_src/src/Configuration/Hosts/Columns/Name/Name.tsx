import {
  type ComponentColumnProps,
  HostIcon,
  Image,
  truncate
} from '@centreon/ui';

import type { JSX } from 'react';

import type { Icon } from '../../models';

const iconClassName = 'size-4 shrink-0';

const Name = ({
  row,
  renderEllipsisTypography
}: ComponentColumnProps): JSX.Element => {
  const icon = row.icon as Icon | undefined | null;

  const name = renderEllipsisTypography?.({
    formattedString: truncate({ content: row.name as string, maxLength: 50 })
  });

  return (
    <div className="flex items-center gap-1 overflow-hidden">
      {/* `Image` renders nothing when `imagePath` is nil: it never falls back.
          The explicit branch is what shows the default icon. */}
      {icon?.url ? (
        <Image
          // `useLoadImage` caches by `alt`, so it must identify the icon, not the row.
          alt={icon.name}
          className={iconClassName}
          fallback={<HostIcon className={iconClassName} />}
          imagePath={icon.url}
        />
      ) : (
        <HostIcon className={iconClassName} />
      )}
      {name}
    </div>
  );
};

export default Name;
