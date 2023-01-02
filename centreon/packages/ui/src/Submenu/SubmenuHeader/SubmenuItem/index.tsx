import { makeStyles } from 'tss-react/mui';
import { Link } from 'react-router-dom';
import { equals } from 'ramda';

import { ThemeMode } from '@centreon/ui-context';

import { SeverityCode } from '../../../StatusChip';
import { getStatusColors } from '../../..';

export interface StyleProps {
  severityCode: SeverityCode;
}

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => ({
  count: {
    marginLeft: 'auto'
  },
  link: {
    alignItems: 'center',
    color: 'inherit',
    display: 'flex',
    padding: theme.spacing(1),
    textDecoration: 'none'
  },
  status: {
    alignItems: 'center',
    display: 'flex'
  },
  statusCounter: {
    background: getStatusColors({
      severityCode,
      theme
    })?.backgroundColor,
    borderRadius: '50%',
    height: theme.spacing(1),
    marginRight: theme.spacing(1),
    width: theme.spacing(1)
  },
  submenuItem: {
    '&:hover': {
      background: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.primary.dark
        : theme.palette.primary.light,
      color: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.common.white
        : theme.palette.primary.main
    },
    '&:not(:last-child)': {
      borderBottom: `1px solid ${theme.palette.divider}`
    }
  }
}));

interface Props {
  countTestId?: string;
  onClick: (e: React.MouseEvent) => void;
  severityCode: SeverityCode;
  submenuCount: string | number;
  submenuTitle: string;
  titleTestId?: string;
  to: string;
}

const SubmenuItem = ({
  onClick,
  severityCode,
  submenuTitle,
  submenuCount,
  titleTestId,
  countTestId,
  to
}: Props): JSX.Element => {
  const { classes } = useStyles({ severityCode });

  return (
    <li className={classes.submenuItem}>
      <Link className={classes.link} to={to} onClick={onClick}>
        <span className={classes.status} data-testid={titleTestId}>
          <span className={classes.statusCounter} />
          <span>{submenuTitle}</span>
        </span>
        <span className={classes.count} data-testid={countTestId}>
          {submenuCount}
        </span>
      </Link>
    </li>
  );
};

export default SubmenuItem;
