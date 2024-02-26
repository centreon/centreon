import { makeStyles } from 'tss-react/mui';

import { SeverityCode, getStatusColors } from '@centreon/ui';

interface StylesProps {
  severityCode?: SeverityCode;
}

const useStyles = makeStyles<StylesProps>()((theme, { severityCode }) => ({
  avatar: {
    ...getStatusColors({ severityCode, theme }),
    fontSize: theme.typography.caption.fontSize,
    height: theme.spacing(2),
    width: theme.spacing(2)
  },
  status: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(0.5),
    marginRight: theme.spacing(1)
  },
  statusCount: {
    alignItems: 'center',
    display: 'flex'
  }
}));

export default useStyles;
