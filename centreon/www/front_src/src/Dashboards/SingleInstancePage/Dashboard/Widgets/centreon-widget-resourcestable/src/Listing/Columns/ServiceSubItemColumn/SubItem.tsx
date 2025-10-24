import { Box } from '@mui/material';
import { isNil, keys } from 'ramda';
import { ReactElement } from 'react';

import { ComponentColumnProps } from '@centreon/ui';

import { getStatus } from '../../utils';
import StatusChip from './StatusChip';
import useStyles from './SubItem.styles';

const SubItem = ({ row }: ComponentColumnProps): ReactElement => {
  const { classes } = useStyles({});

  const statusCount = row?.children?.status_count ?? {};
  const isNestedRow = isNil(row?.children);

  return (
    <Box className={classes.statusCount}>
      {row?.resource_name && isNestedRow && (
        <Box className={classes.nestedStatus}>
          <StatusChip
            content={getStatus(row?.status.name.toLowerCase())?.label}
            severityCode={getStatus(row?.status.name.toLowerCase())?.severity}
          />
          <p>{row?.resource_name}</p>
        </Box>
      )}

      {keys(statusCount).map((item) => {
        if (statusCount[item]) {
          return (
            <Box className={classes.status} key={item as string}>
              <StatusChip
                content={getStatus(item as string).label}
                severityCode={getStatus(item as string).severity}
              />
              <p>({statusCount[item]})</p>
            </Box>
          );
        }

        return null;
      })}
    </Box>
  );
};

export default SubItem;
