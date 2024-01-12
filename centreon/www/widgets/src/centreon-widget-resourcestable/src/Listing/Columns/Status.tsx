import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import type { ComponentColumnProps } from '@centreon/ui';
import { SeverityCode, StatusChip, useStyleTable } from '@centreon/ui';

import { useStatusStyles } from './Columns.styles';

const StatusColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { dataStyle } = useStyleTable({});
  const { classes } = useStatusStyles({
    data: dataStyle.statusColumnChip
  });
  const { t } = useTranslation();

  const statusName = row.status.name;

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
