import { equals, isNil } from 'ramda';

import type { ComponentColumnProps } from '@centreon/ui';
import { SeverityCode, StatusChip } from '@centreon/ui';

import { DisplayType } from '../models';

const StatusColumn =
  ({ displayType, classes, t }) =>
  ({ row }: ComponentColumnProps): JSX.Element => {
    const statusName = row.status.name;

    const isNestedRow =
      equals(displayType, DisplayType.Host) && isNil(row?.isHeadRow);

    if (isNestedRow) {
      return <div />;
    }

    const label = equals(SeverityCode[5], statusName) ? (
      <>{t(statusName)}</>
    ) : (
      t(statusName)
    );

    return (
      <div className={classes.statusColumn}>
        <StatusChip
          className={classes.statusColumnChip}
          label={label}
          severityCode={row.status.severity_code}
        />
      </div>
    );
  };

export default StatusColumn;
