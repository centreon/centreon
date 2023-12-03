import DescriptionIcon from '@mui/icons-material/DescriptionOutlined';

import { Tooltip } from '@centreon/ui/components';
import { ComponentColumnProps } from '@centreon/ui';

import { useColumnStyles } from './useColumnStyles';

const Description = ({ row }: ComponentColumnProps) : JSX.Element => {
  const { classes } = useColumnStyles();
  const description = row?.description;

  return (
    <Tooltip label={description}>
      <DescriptionIcon className={classes.icon} color="primary" />
    </Tooltip>
  );
};

export default Description;
