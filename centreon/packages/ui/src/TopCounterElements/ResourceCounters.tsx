import { Fragment } from 'react';

import { Link } from 'react-router-dom';
import { makeStyles } from 'tss-react/mui';

import { SeverityCode } from '@centreon/ui';

import StatusCounter from './StatusCounter';

const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'inline-block',
    listStyle: 'none',
    margin: 0,
    padding: 0,
    [theme.breakpoints.down(768)]: {
      flexFlow: 'row wrap'
    }
  },
  item: {
    display: 'inline-block',
    margin: 0,
    padding: 0,
    paddingRight: theme.spacing(0.25)
  },
  link: {
    textDecoration: 'none'
  },
  splitter: {
    display: 'none',
    [theme.breakpoints.down(768)]: {
      display: 'block',
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
            {index === 2 && (
              <li aria-hidden="true" className={classes.splitter} />
            )}
            <li className={classes.item}>
              <Link
                aria-label={ariaLabel}
                className={classes.link}
                to={to}
                onClick={onClick}
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
