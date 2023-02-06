import { makeStyles } from "tss-react/mui";
import { Typography, Button } from "@mui/material";
import ExportConfiguration from "./ExportConfiguration";

const useStyles = makeStyles()((theme) => ({
  list: {
    listStyle: "none",
    padding: 0,
    margin: 0,
  },
  listItem: {
    listStyle: "none",
    padding: theme.spacing(1),
    margin: 0,
    "&:not(:last-child)": {
      borderBottom: `1px solid ${theme.palette.divider}`,
    },
  },
  link: {
    textDecoration: "none",
  },
  pollarHeaderRight: {
    display: "flex",
    flexDirection: "column",
    justifyContent: "space-between",
    [theme.breakpoints.down(768)]: {
      flexDirection: "row",
      gap: theme.spacing(0.5),
    },
  },
  pollerDetailRow: {
    display: "flex",
    justifyContent: "space-between",
  },
  pollerDetailTitle: {
    flexGrow: 1,
  },
}));

export const PollerSubMenu = ({
  closeSubMenu,
  issues,
  pollerCount,
  allPollerLabel,
  pollerConfig,
  newExporting,
  exportConfig,
}) => {
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
            variant="outlined"
            data-testid={pollerConfig.testId}
            size="small"
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
