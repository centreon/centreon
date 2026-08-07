// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { type ComponentColumnProps, truncate } from '@centreon/ui';

import { equals, isNil } from 'ramda';

import { DisplayType } from '../models';
import { getStatus } from '../utils';
import StatusChip from './ServiceSubItemColumn/StatusChip';
import ShortTypeChip from './ShortTypeChip';

const ResourceColumn =
  ({ displayType }: { displayType: DisplayType }) =>
  ({ row, renderEllipsisTypography }: ComponentColumnProps): JSX.Element => {
    const typedRow = row as {
      status?: { name?: string };
      children?: unknown;
      name?: string;
      resource_name?: string;
      icon?: { name?: string; url?: string };
      short_type?: string;
    };
    const isViewByHostMode = equals(displayType, DisplayType.Host);
    const isViewByServiceMode = equals(displayType, DisplayType.Service);
    const status = typedRow?.status?.name;
    const isNestedRow = isNil(typedRow?.children) && isViewByHostMode;

    const resourceName = renderEllipsisTypography?.({
      className: 'pl-1',
      formattedString: truncate({
        content: (typedRow.name || typedRow.resource_name) as string,
        maxLength: 50
      })
    });

    if (isNestedRow) {
      return <div />;
    }

    if (isViewByHostMode) {
      return (
        <div className="flex">
          <div className="mr-1">
            <StatusChip
              content={getStatus(status?.toLowerCase())?.label}
              severityCode={getStatus(status?.toLowerCase())?.severity}
            />
          </div>
          {typedRow?.icon && (
            <img
              alt={typedRow.icon.name}
              height={16}
              src={typedRow.icon.url}
              width={16}
            />
          )}

          {resourceName}
        </div>
      );
    }

    return (
      <>
        <div className="flex items-center flex-nowrap">
          {!isViewByServiceMode && !typedRow.icon && (
            <ShortTypeChip label={typedRow.short_type as string} />
          )}
          {typedRow.icon && (
            <img
              alt={typedRow.icon.name}
              height={16}
              src={typedRow.icon.url}
              width={16}
            />
          )}
        </div>
        {resourceName}
      </>
    );
  };

export default ResourceColumn;
