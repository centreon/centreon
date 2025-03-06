import { keys } from 'ramda';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { getStatus } from '../../utils';

import StatusChip from './StatusChip';
import useStyles from './SubItem.styles';

const SubItem = ({ row }: ComponentColumnProps): JSX.Element => {
  const { resourceCount } = row;
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
      {keys(resourceCount)?.map((item) => {
        if (resourceCount?.[item]) {
          return (
            <Box className={classes.status} key={item as string}>
              <StatusChip
                content={getStatus(item as string).label}
                severityCode={getStatus(item as string).severity}
              />
              <p>({resourceCount?.[item]})</p>
            </Box>
          );
        }

        return <Box key={item as string} />;
      })}
    </Box>
  );
};

export default SubItem;
