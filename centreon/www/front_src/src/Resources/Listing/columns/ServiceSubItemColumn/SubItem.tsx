import { Box } from '@mui/material';

import { ComponentColumnProps, SeverityCode } from '@centreon/ui';

import { cond, equals, isNil, keys } from 'ramda';

import StatusChip from './StatusChip';
import useStyles from './SubItem.styles';

export const getStatus = cond([
  [equals('ok'), () => ({ label: 'O', severity: SeverityCode.OK })],
  [equals('up'), () => ({ label: 'U', severity: SeverityCode.OK })],
  [equals('warning'), () => ({ label: 'W', severity: SeverityCode.Medium })],
  [equals('critical'), () => ({ label: 'C', severity: SeverityCode.High })],
  [equals('unknown'), () => ({ label: 'U', severity: SeverityCode.Low })],
  [equals('pending'), () => ({ label: 'P', severity: SeverityCode.Pending })]
]);

const SubItem = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles({});

  const typedRow = row as unknown as {
    children?: unknown;
    childrenCount?: Record<string, number>;
    resource_name?: string;
    status: { name: string };
  };

  const statusCount = typedRow?.childrenCount;
  const isNestedRow = isNil(typedRow?.children);

  return (
    <Box className={classes.statusCount}>
      {typedRow?.resource_name && isNestedRow && (
        <Box className={classes.nestedStatus}>
          <StatusChip
            content={getStatus(typedRow?.status.name.toLowerCase())?.label}
            severityCode={
              getStatus(typedRow?.status.name.toLowerCase())?.severity
            }
          />
          <p>{typedRow?.resource_name}</p>
        </Box>
      )}

      {keys(statusCount ?? {})?.map((item) => {
        if (statusCount?.[item as string]) {
          return (
            <Box className={classes.status} key={item as string}>
              <StatusChip
                content={getStatus(item as string).label}
                severityCode={getStatus(item as string).severity}
              />
              <p>({statusCount?.[item as string]})</p>
            </Box>
          );
        }

        return <Box key={item as string} />;
      })}
    </Box>
  );
};

export default SubItem;
