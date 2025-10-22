import { equals, isNil } from 'ramda';

import { type ComponentColumnProps, truncate } from '@centreon/ui';

import { DisplayType } from '../models';
import { getStatus } from '../utils';
import useStyles from './Columns.styles';
import StatusChip from './ServiceSubItemColumn/StatusChip';
import ShortTypeChip from './ShortTypeChip';

const ResourceColumn =
  ({ displayType }) =>
  ({ row, renderEllipsisTypography }: ComponentColumnProps): JSX.Element => {
    const { classes } = useStyles();

    const isViewByHostMode = equals(displayType, DisplayType.Host);
    const isViewByServiceMode = equals(displayType, DisplayType.Service);
    const status = row?.status.name;
    const isNestedRow = isNil(row?.children) && isViewByHostMode;

    const resourceName = renderEllipsisTypography?.({
      className: classes.resourceNameText,
      formattedString: truncate({
        content: row.name || row.resource_name,
        maxLength: 50
      })
    });

    if (isNestedRow) {
      return <div />;
    }

    if (isViewByHostMode) {
      return (
        <div className="flex">
          <div className={classes.statusChip}>
            <StatusChip
              content={getStatus(status?.toLowerCase())?.label}
              severityCode={getStatus(status?.toLowerCase())?.severity}
            />
          </div>
          {row?.icon && (
            <img
              alt={row.icon.name}
              height={16}
              src={row.icon.url}
              width={16}
            />
          )}

          {resourceName}
        </div>
      );
    }

    return (
      <>
        <div className={classes.resourceDetailsCell}>
          {!isViewByServiceMode && !row.icon && (
            <ShortTypeChip label={row.short_type} />
          )}
          {row.icon && (
            <img
              alt={row.icon.name}
              height={16}
              src={row.icon.url}
              width={16}
            />
          )}
        </div>
        {resourceName}
      </>
    );
  };

export default ResourceColumn;
