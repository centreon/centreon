import type { SeverityCode } from '@centreon/ui';

import { Fragment } from 'react';
import { Link } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import StatusCounter from './StatusCounter';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    listStyle: 'none',
    margin: 0,
    padding: 0,
    [theme.breakpoints.down(1025)]: {
      flexFlow: 'row wrap'
    }
  },
  item: {
    alignItems: 'center',
    display: 'flex',
    margin: 0,
    padding: 0,
    paddingRight: theme.spacing(0.25)
  },
  link: {
    alignItems: 'center',
    display: 'flex',
    textDecoration: 'none'
  },
  splitter: {
    display: 'none',
    [theme.breakpoints.down(1025)]: {
      display: 'block',
      flexBasis: '100%',
      marginBottom: theme.spacing(0.25)
    }
  }
}));

export interface CounterProps {
  counters: Array<{
    ariaLabel: string;
    count: string | number;
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
        ({ to, ariaLabel, onClick, count, severityCode }, index) => (
          <Fragment key={to.toString().replace(/\W/g, '')}>
            {index === 2 && <li className={classes.splitter} />}
            <li className={classes.item}>
              <Link
                aria-label={ariaLabel}
                className={classes.link}
                onClick={onClick}
                to={to}
              >
                <StatusCounter count={count} severityCode={severityCode} />
              </Link>
            </li>
          </Fragment>
        )
      )}
    </ul>
  );
};
