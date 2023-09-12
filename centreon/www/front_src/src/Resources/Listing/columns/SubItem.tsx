import { cond, equals, keys } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Avatar } from '@mui/material';

import {
  ComponentColumnProps,
  SeverityCode,
  getStatusColors
} from '@centreon/ui';

const getStatus = cond([
  [equals('ok'), () => ({ label: 'O', severity: SeverityCode.OK })],
  [equals('warning'), () => ({ label: 'W', severity: SeverityCode.Medium })],
  [equals('critical'), () => ({ label: 'C', severity: SeverityCode.High })],
  [equals('unknown'), () => ({ label: 'U', severity: SeverityCode.Low })]
]);

interface StylesProps {
  severityCode: SeverityCode;
}
const useStyles = makeStyles<StylesProps>()((theme, { severityCode }) => ({
  avatar: {
    ...getStatusColors({ severityCode, theme })
  }
}));

export type Props = {
  content: string;
  severityCode: SeverityCode;
};

const StatusAvatar = ({ content, severityCode }: Props): JSX.Element => {
  const { classes } = useStyles({
    severityCode
  });

  return <Avatar className={classes.avatar}>{content}</Avatar>;
};

const SubItem = ({ row }: ComponentColumnProps): JSX.Element => {
  const statusCount = row?.childrenCount;

  return (
    <div>
      {keys(statusCount)?.map((item) => {
        return (
          <div key={item as string}>
            {/* <Badge
              badgeContent={getStatus(item as string).label}
              //   classes={{
              //     badge: cx(classes.badge, className)
              //   }}
              max={Infinity}
              overlap="circular"
            /> */}
            {/* <StatusChip
              label={getStatus(item as string).label}
              severityCode={getStatus(item as string).severity}
              size="small"
            /> */}

            {/* <Avatar sx={{ bgcolor: 'orange' }}>
              {getStatus(item as string).label}
            </Avatar> */}
            <StatusAvatar
              content={getStatus(item as string).label}
              severityCode={getStatus(item as string).severity}
            />
            <div>( {statusCount[item]} )</div>
          </div>
        );
      })}
    </div>
  );
};

export default SubItem;
