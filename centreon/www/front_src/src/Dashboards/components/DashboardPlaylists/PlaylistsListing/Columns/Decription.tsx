import { Tooltip } from '@centreon/ui/components';
import DescriptionIcon from '@mui/icons-material/DescriptionOutlined';
import { ComponentColumnProps } from '@centreon/ui';
import { useColumnStyles } from './useColumnStyles';


const Description = ( { row }: ComponentColumnProps) => {
   
  const {  classes } = useColumnStyles();
  const description = row?.description;
  
  return (
    <Tooltip label={description}>
      <DescriptionIcon color='primary' className={classes.icon } />
    </Tooltip>
  );
};

export default Description