import { Avatar } from '@mui/material';

import { SeverityCode } from '@centreon/ui';

import useStyles from './SubItem.styles';

export type Props = {
  content: string;
  severityCode: SeverityCode;
};

const StatusChip = ({ content, severityCode }: Props): JSX.Element => {
  const { classes } = useStyles({
    severityCode
  });

  return <Avatar className={classes.avatar}>{content}</Avatar>;
};

export default StatusChip;
