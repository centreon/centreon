import { cond, equals, keys } from 'ramda';

import { Box } from '@mui/material';

import { ComponentColumnProps, SeverityCode } from '@centreon/ui';

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
  const statusCount = row?.childrenCount;
  const { classes } = useStyles({});

  return (
    <Box className={classes.statusCount}>
      {row?.resource_name && (
        <Box className={classes.status}>
          <StatusChip
            content={getStatus(row?.status.name.toLowerCase())?.label}
            severityCode={getStatus(row?.status.name.toLowerCase())?.severity}
          />
          <p>{row?.resource_name}</p>
        </Box>
      )}
      {keys(statusCount)?.map((item) => {
        if (statusCount?.[item]) {
          return (
            <Box className={classes.status} key={item as string}>
              <StatusChip
                content={getStatus(item as string).label}
                severityCode={getStatus(item as string).severity}
              />
              <p>({statusCount?.[item]})</p>
            </Box>
          );
        }

        return <Box key={item as string} />;
      })}
    </Box>
  );
};

export default SubItem;
