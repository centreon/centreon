import { isNil } from 'ramda';

import DescriptionIcon from '@mui/icons-material/DescriptionOutlined';
import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import { useColumnStyles } from './useColumnStyles';

const Description = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useColumnStyles();

  const { role, description } = row;

  const isNestedRow = !isNil(role);

  if (isNestedRow) {
    return <Box />;
  }

  return (
    <Tooltip label={description}>
      {description ? (
        <DescriptionIcon className={classes.icon} color="primary" />
      ) : (
        <Box className={classes.line}>-</Box>
      )}
    </Tooltip>
  );
};

export default Description;
