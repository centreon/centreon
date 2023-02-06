import * as React from "react";

import { makeStyles } from "tss-react/mui";
import { Link } from "react-router-dom";
import { equals } from "ramda";

import { ThemeMode } from "@centreon/ui-context";

import { SeverityCode, getStatusColors } from "@centreon/ui";

const useStyles = makeStyles()((theme) => ({
  submenu: {
    color: theme.palette.text.primary,
    fontSize: theme.typography.body2.fontSize,
    listStyle: "none",
    margin: 0,
    padding: 0,
  },
  count: {
    marginLeft: "auto",
  },
  link: {
    alignItems: "center",
    color: "inherit",
    display: "flex",
    padding: theme.spacing(1),
    textDecoration: "none",
  },
  status: {
    alignItems: "center",
    display: "flex",
  },
  statusCounter: {
    borderRadius: "50%",
    height: theme.spacing(1),
    marginRight: theme.spacing(1),
    width: theme.spacing(1),
  },
  submenuItem: {
    "&:hover": {
      background: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.primary.dark
        : theme.palette.primary.light,
      color: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.common.white
        : theme.palette.primary.main,
    },
    "&:not(:last-child)": {
      borderBottom: `1px solid ${theme.palette.divider}`,
    },
  },
}));

export interface SubMenuProps {
  items: {
    onClick: (e: React.MouseEvent) => void;
    severityCode: SeverityCode;
    submenuCount: string | number;
    submenuTitle: string;
    to: string;
    countTestId?: string;
  }[];
}

const SubMenu = ({ items }: SubMenuProps): JSX.Element => {
  const { classes, theme } = useStyles();

  return (
    <ul className={classes.submenu}>
      {items.map(
        ({
          onClick,
          severityCode,
          submenuTitle,
          submenuCount,
          countTestId,
          to,
        }) => (
          <li className={classes.submenuItem} key={to}>
            <Link className={classes.link} to={to} onClick={onClick}>
              <span className={classes.status}>
                <span
                  className={classes.statusCounter}
                  style={{
                    backgroundColor: getStatusColors({ severityCode, theme })
                      ?.backgroundColor,
                  }}
                />
                <span>{submenuTitle}</span>
              </span>
              <span className={classes.count} data-testid={countTestId}>
                {submenuCount}
              </span>
            </Link>
          </li>
        )
      )}
    </ul>
  );
};

export default SubMenu;
