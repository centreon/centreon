import { isNil } from 'ramda';

import DescriptionIcon from '@mui/icons-material/DescriptionOutlined';
import { Box } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';
import { ComponentColumnProps } from '@centreon/ui';

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
      <DescriptionIcon className={classes.icon} color="primary" />
    </Tooltip>
  );
};

export default Description;
