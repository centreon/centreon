import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { isNil, keys } from 'ramda';
import { ReactElement } from 'react';

import { getStatus } from '../../utils';
import StatusChip from './StatusChip';
import useStyles from './SubItem.styles';

const SubItem = ({ row }: ComponentColumnProps): ReactElement => {
  const { classes } = useStyles({});

  const typedRow = row as {
    children?: { status_count?: Record<string, number> };
    status?: { name?: string };
    resource_name?: string;
  };

  const statusCount = typedRow?.children?.status_count ?? {};
  const isNestedRow = isNil(typedRow?.children);

  return (
    <Box className={classes.statusCount}>
      {typedRow?.resource_name && isNestedRow && (
        <Box className={classes.nestedStatus}>
          <StatusChip
            content={
              getStatus(typedRow?.status?.name?.toLowerCase() as string)?.label
            }
            severityCode={
              getStatus(typedRow?.status?.name?.toLowerCase() as string)
                ?.severity
            }
          />
          <p>{typedRow?.resource_name}</p>
        </Box>
      )}

      {keys(statusCount).map((item) => {
        if (statusCount[item as string]) {
          return (
            <Box className={classes.status} key={item as string}>
              <StatusChip
                content={getStatus(item as string).label}
                severityCode={getStatus(item as string).severity}
              />
              <p>({statusCount[item as string]})</p>
            </Box>
          );
        }

        return null;
      })}
    </Box>
  );
};

export default SubItem;
