import StatusCounter from "./StatusCounter";
import { Link } from "react-router-dom";

import { makeStyles } from "tss-react/mui";
import { SeverityCode } from "@centreon/ui";

const useStyles = makeStyles()((theme) => ({
  container: {
    listStyle: "none",
    padding: 0,
    margin: 0,
    display: "inline-block",
    lineHeight: 1,
    [theme.breakpoints.down(768)]: {
      flexFlow: "row wrap",
    },
  },
  item: {
    display: "inline-block",
    paddingRight: theme.spacing(0.25),
  },
  splitter: {
    display: "none",
    [theme.breakpoints.down(768)]: {
      display: "block",
      marginBottom: "2px",
    },
  },
  link: {
    textDecoration: "none",
  },
}));

export interface CounterProps {
  counters: {
    onClick: (e: React.MouseEvent) => void;
    severityCode: SeverityCode;
    count: string | number;
    ariaLabel: string;
    to: string;
  }[];
}

export default ({ counters }: CounterProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <ul className={classes.container}>
      {counters.map(
        ({ to, ariaLabel, onClick, count, severityCode }, index) => (
          <>
            {index === 2 && (
              <li
                key={`${to}-splitter`}
                className={classes.splitter}
                aria-hidden="true"
              />
            )}
            <li key={to} className={classes.item}>
              <Link
                className={classes.link}
                aria-label={ariaLabel}
                to={to}
                onClick={onClick}
              >
                <StatusCounter count={count} severityCode={severityCode} />
              </Link>
            </li>
          </>
        )
      )}
    </ul>
  );
};
