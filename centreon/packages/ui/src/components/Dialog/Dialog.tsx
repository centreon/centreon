import React from "react";

import {
  Dialog as MuiDialog,
  DialogProps as MuiDialogProps,
} from "@mui/material";

import { useStyles } from "./Dialog.styles";

type DialogProps = {
  children: React.ReactNode;
  onClose?: (event: object, reason: "escapeKeyDown" | "backdropClick") => void;
  open: MuiDialogProps["open"];
};

/** *
 * @description This component is *WIP* and is not ready for production. Use the default `Dialog` component instead.
 */
const Dialog = ({ children, ...dialogProps }: DialogProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <MuiDialog className={classes.dialog} {...dialogProps}>
      {children}
    </MuiDialog>
  );
};

export { Dialog };
