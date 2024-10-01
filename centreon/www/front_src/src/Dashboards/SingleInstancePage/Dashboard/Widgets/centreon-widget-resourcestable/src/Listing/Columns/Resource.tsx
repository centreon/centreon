import { equals } from 'ramda';

import { type ComponentColumnProps, EllipsisTypography } from '@centreon/ui';

import { DisplayType } from '../models';
import { getStatus } from '../utils';

import StatusChip from './ServiceSubItemColumn/StatusChip';
import ShortTypeChip from './ShortTypeChip';

const ResourceColumn =
  ({ displayType, classes }) =>
  ({ row }: ComponentColumnProps): JSX.Element => {
    const isViewByHostMode = equals(displayType, DisplayType.Host);
    const isViewByServiceMode = equals(displayType, DisplayType.Service);
    const status = row?.status.name;

    const resourceName = (
      <EllipsisTypography className={classes.resourceNameText} variant="body2">
        {row.name}
      </EllipsisTypography>
    );

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
