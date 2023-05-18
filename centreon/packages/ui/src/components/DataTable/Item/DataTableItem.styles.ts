import { makeStyles } from "tss-react/mui";

const useStyles = makeStyles()((theme) => ({
  dataTableItem: {
    "& .MuiCardActionArea-root": {
      alignItems: "flex-start",
      display: "flex",
      flexDirection: "column",
      height: "100%",
      justifyContent: "space-between",
    },
    "& .MuiCardActions-root": {
      display: "flex",
      flexDirection: "row-reverse",
      gap: theme.spacing(1),

      opacity: 0,
    },
    "&:hover .MuiCardActions-root": {
      opacity: 1,
    },
    borderRadius: theme.shape.borderRadius,
    display: "flex",
    flexDirection: "column",
    height: "210px",
    minWidth: "280px",
    p: {
      color: theme.palette.text.secondary,
      letterSpacing: "0",
      margin: "0",
    },
    width: "320px",
  },
}));

export { useStyles };
