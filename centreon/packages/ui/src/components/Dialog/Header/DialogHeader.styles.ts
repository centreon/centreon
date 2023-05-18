import { makeStyles } from "tss-react/mui";

const useStyles = makeStyles()((theme) => ({
  dialogHeader: {
    padding: theme.spacing(0, 0, 2, 0),
    display: "flex",
    justifyContent: "space-between",
    gap: theme.spacing(2),

    "& .MuiDialogTitle-root": {
      padding: theme.spacing(0),
    },

    "& > button": {
      transform: "translate(5px, 0px)",
      svg: {
        opacity: 0.6,
      },
    },
  },
}));

export { useStyles };
