import { makeStyles } from "tss-react/mui";

const useStyles = makeStyles()((theme) => ({
  header: {
    display: "flex",
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    padding: theme.spacing(0, 0, 1.5, 0),
    marginBottom: theme.spacing(2.5),
    borderBottom: `1px solid ${theme.palette.primary.main}`,

    h1: {
      font: "normal normal 600 24px/24px Roboto",
      letterSpacing: "0.18px",
      margin: theme.spacing(0, 0, 1.5, 0),
    },

    nav: {
      display: "flex",
      gap: theme.spacing(1),
      justifyContent: "flex-end",
    },
  },
}));

export { useStyles };
