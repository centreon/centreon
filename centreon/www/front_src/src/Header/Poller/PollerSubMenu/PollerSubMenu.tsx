import { makeStyles } from 'tss-react/mui';

import { Typography, Button } from '@mui/material';

import ExportConfiguration from './ExportConfiguration';

const useStyles = makeStyles()((theme) => ({
  link: {
    textDecoration: 'none'
  },
  list: {
    listStyle: 'none',
    margin: 0,
    padding: 0
  },
  listItem: {
    '&:not(:last-child)': {
      borderBottom: `1px solid ${theme.palette.divider}`
    },
    listStyle: 'none',
    margin: 0,
    padding: theme.spacing(1)
  },
  pollarHeaderRight: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between',
    [theme.breakpoints.down(768)]: {
      flexDirection: 'row',
      gap: theme.spacing(0.5)
    }
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
    <ul className={classes.list}>
      {issues.length > 0 ? (
        issues.map(({ text, total, key }) => {
          return (
            <li
              className={cx(classes.listItem, classes.pollerDetailRow)}
              key={key}
            >
              <Typography className={classes.pollerDetailTitle} variant="body2">
                {text}
              </Typography>
              <Typography variant="body2">{total}</Typography>
            </li>
          );
        })
      ) : (
        <li className={cx(classes.listItem, classes.pollerDetailRow)}>
          <Typography variant="body2">{allPollerLabel}</Typography>
          <Typography variant="body2">{pollerCount as number}</Typography>
        </li>
      )}
      {pollerConfig.isAllowed && (
        <li className={classes.listItem}>
          <Button
            fullWidth
            data-testid={pollerConfig.testId}
            size="small"
            variant="outlined"
            onClick={pollerConfig.redirect}
          >
            {pollerConfig.label}
          </Button>
        </li>
      )}
      {exportConfig.isExportButtonEnabled && (
        <li className={classes.listItem}>
          <ExportConfiguration closeSubMenu={closeSubMenu} />
        </li>
      )}
    </ul>
  );
};
