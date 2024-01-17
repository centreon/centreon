import { equals } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';

import { DisplayType } from '../models';

import ShortTypeChip from './ShortTypeChip';
import StatusChip from './ServiceSubItemColumn/StatusChip';
import { getStatus } from './ServiceSubItemColumn/SubItem';

const ResourceColumn =
  ({ displayType, classes }) =>
  ({ row, renderEllipsisTypography }: ComponentColumnProps): JSX.Element => {
    const isViewByHostMode = equals(displayType, DisplayType.Host);
    const isViewByServiceMode = equals(displayType, DisplayType.Service);
    const status = row?.status.name;

    const resourceName = renderEllipsisTypography?.({
      className: classes.resourceNameText,
      formattedString: row.name
    });

    if (isViewByServiceMode) {
      return <div>{resourceName}</div>;
    }

    if (isViewByHostMode) {
      return (
        <div>
          {equals(row?.type, 'host') && (
            <>
              <StatusChip
                content={getStatus(status?.toLowerCase())?.label}
                severityCode={getStatus(status?.toLowerCase())?.severity}
              />
              {row?.icon && (
                <img
                  alt={row.icon.name}
                  height={16}
                  src={row.icon.url}
                  width={16}
                />
              )}
            </>
          )}
          {resourceName}
        </div>
      );
    }

    return (
      <>
        <div className={classes.resourceDetailsCell}>
          {row.icon ? (
            <img
              alt={row.icon.name}
              height={16}
              src={row.icon.url}
              width={16}
            />
          ) : (
            <ShortTypeChip label={row.short_type} />
          )}
        </div>
        {resourceName}
      </>
    );
  };

export default ResourceColumn;
