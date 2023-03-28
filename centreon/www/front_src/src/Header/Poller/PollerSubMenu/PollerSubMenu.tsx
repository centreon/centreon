import { makeStyles } from 'tss-react/mui';
import { isEmpty } from 'ramda';

import { Typography, Button, List, ListItem } from '@mui/material';

import ExportConfiguration from './ExportConfiguration';

const useStyles = makeStyles()((theme) => ({
  link: {
    textDecoration: 'none'
  },
  list: {
    minWidth: theme.spacing(27),
    padding: 0
  },
  listItem: {
    '&:not(:last-child)': {
      borderBottom: `1px solid ${theme.palette.divider}`
    },
    padding: theme.spacing(1)
  },
  pollerDetailRow: {
    display: 'flex',
    justifyContent: 'space-between'
  },
  pollerDetailTitle: {
    flexGrow: 1
  }
}));

export interface PollerSubMenuProps {
  allPollerLabel: string;
  closeSubMenu: () => void;
  exportConfig: {
    isExportButtonEnabled: boolean;
  };
  issues: Array<{
    key: string;
    text: string;
    total: string;
  }>;
  pollerConfig: {
    isAllowed: boolean;
    label: string;
    redirect: () => void;
    testId: string;
  };
  pollerCount: number;
}

export const PollerSubMenu = ({
  closeSubMenu,
  issues,
  pollerCount,
  allPollerLabel,
  pollerConfig,
  exportConfig
}: PollerSubMenuProps): JSX.Element => {
  const { classes, cx } = useStyles();

  return (
    <List className={classes.list} data-testid="poller-menu">
      {!isEmpty(issues) ? (
        issues.map(({ text, total, key }) => {
          return (
            <ListItem
              className={cx(classes.listItem, classes.pollerDetailRow)}
              key={key}
            >
              <Typography className={classes.pollerDetailTitle} variant="body2">
                {text}
              </Typography>
              <Typography variant="body2">{total}</Typography>
            </ListItem>
          );
        })
      ) : (
        <ListItem className={cx(classes.listItem, classes.pollerDetailRow)}>
          <Typography variant="body2">{allPollerLabel}</Typography>
          <Typography variant="body2">{pollerCount as number}</Typography>
        </ListItem>
      )}
      {pollerConfig.isAllowed && (
        <ListItem className={classes.listItem}>
          <Button
            fullWidth
            data-testid={pollerConfig.testId}
            size="small"
            variant="outlined"
            onClick={pollerConfig.redirect}
          >
            {pollerConfig.label}
          </Button>
        </ListItem>
      )}
      {exportConfig.isExportButtonEnabled && (
        <ListItem className={classes.listItem}>
          <ExportConfiguration closeSubMenu={closeSubMenu} />
        </ListItem>
      )}
    </List>
  );
};
