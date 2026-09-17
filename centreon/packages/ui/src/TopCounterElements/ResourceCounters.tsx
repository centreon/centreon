import type { SeverityCode } from '@centreon/ui';

import { Link } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import StatusCounter from './StatusCounter';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1),
    listStyle: 'none',
    margin: 0,
    padding: 0
  },
  item: {
    alignItems: 'center',
    display: 'flex',
    margin: 0,
    padding: 0
  },
  link: {
    alignItems: 'center',
    display: 'flex',
    textDecoration: 'none'
  }
}));

export interface CounterProps {
  counters: Array<{
    ariaLabel: string;
    count: string | number;
    detail?: string | number;
    onClick: (e: React.MouseEvent) => void;
    severityCode: SeverityCode;
    to: string;
  }>;
}

export default ({ counters }: CounterProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ul className={classes.container}>
      {counters.map(
        ({ to, ariaLabel, onClick, count, detail, severityCode }) => (
          <li className={classes.item} key={to.toString().replace(/\W/g, '')}>
            <Link
              aria-label={ariaLabel}
              className={classes.link}
              onClick={onClick}
              to={to}
            >
              <StatusCounter
                count={count}
                detail={detail}
                severityCode={severityCode}
              />
            </Link>
          </li>
        )
      )}
    </ul>
  );
};
