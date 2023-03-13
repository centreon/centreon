import * as React from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';
import { Link } from 'react-router-dom';

import { List, ListItem } from '@mui/material';

import { SeverityCode, getStatusColors } from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

const useStyles = makeStyles()((theme) => ({
  count: {
    marginLeft: 'auto'
  },
  link: {
    alignItems: 'center',
    color: 'inherit',
    display: 'flex',
    flex: '100%',
    padding: `0 ${theme.spacing(1)}`,
    textDecoration: 'none'
  },
  status: {
    alignItems: 'center',
    display: 'flex'
  },
  statusCounter: {
    borderRadius: '50%',
    height: theme.spacing(1),
    marginRight: theme.spacing(1),
    width: theme.spacing(1)
  },
  submenu: {
    fontSize: theme.typography.body2.fontSize,
    padding: 0
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

export interface SubMenuProps {
  items: Array<{
    countTestId?: string;
    onClick: (e: React.MouseEvent) => void;
    severityCode: SeverityCode;
    submenuCount: string | number;
    submenuTitle: string;
    to: string;
  }>;
}

const SubMenu = ({ items }: SubMenuProps): JSX.Element => {
  const { classes, theme } = useStyles();

  return (
    <List className={classes.submenu}>
      {items.map(
        ({
          onClick,
          severityCode,
          submenuTitle,
          submenuCount,
          countTestId,
          to
        }) => (
          <ListItem
            disableGutters
            className={classes.submenuItem}
            key={to}
            role="none"
          >
            <Link
              className={classes.link}
              role="menuitem"
              to={to}
              onClick={onClick}
            >
              <span className={classes.status}>
                <span
                  className={classes.statusCounter}
                  style={{
                    backgroundColor: getStatusColors({ severityCode, theme })
                      ?.backgroundColor
                  }}
                />
                <span>{submenuTitle}</span>
              </span>
              <span className={classes.count} data-testid={countTestId}>
                {submenuCount}
              </span>
            </Link>
          </ListItem>
        )
      )}
    </List>
  );
};

export default SubMenu;
