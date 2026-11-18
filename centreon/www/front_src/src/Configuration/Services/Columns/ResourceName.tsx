import { Image, truncate } from '@centreon/ui';

import type { ComponentType, JSX, ReactNode } from 'react';

import type { Icon } from '../models';

const iconClassName = 'size-4 shrink-0';

interface Props {
  DefaultIcon: ComponentType<{ className?: string }>;
  icon?: Icon | null;
  name: string;
  renderEllipsisTypography?: (props: { formattedString: string }) => ReactNode;
}

const ResourceName = ({
  DefaultIcon,
  icon,
  name,
  renderEllipsisTypography
}: Props): JSX.Element => (
  <div className="flex items-center gap-1 overflow-hidden">
    {/* `Image` renders nothing when `imagePath` is nil: it never falls back.
        The explicit branch is what shows the default icon. */}
    {icon?.url ? (
      <Image
        // `useLoadImage` caches by `alt`, so it must identify the icon, not the row.
        alt={icon.name}
        className={iconClassName}
        fallback={<DefaultIcon className={iconClassName} />}
        imagePath={icon.url}
      />
    ) : (
      <DefaultIcon className={iconClassName} />
    )}
    {renderEllipsisTypography?.({
      formattedString: truncate({ content: name, maxLength: 50 })
    })}
  </div>
);

export default ResourceName;
