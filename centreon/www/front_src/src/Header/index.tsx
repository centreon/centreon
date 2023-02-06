import { useRef } from "react";
import { equals } from "ramda";
import { makeStyles } from "tss-react/mui";
import { Theme } from "@mui/material";
import { ThemeMode } from "@centreon/ui-context";

import FederatedComponent from "../components/FederatedComponents";

import Poller from "./Poller";
import HostStatusCounter from "./Ressources/Host";
import ServiceStatusCounter from "./Ressources/Service";
import UserMenu from "./UserMenu";

export const isDarkMode = (theme: Theme): boolean =>
  equals(theme.palette.mode, ThemeMode.dark);

export const headerHeight = 7;

const useStyles = makeStyles()((theme) => ({
  header: {
    alignItems: "center",
    backgroundColor: isDarkMode(theme)
      ? theme.palette.common.black
      : theme.palette.primary.dark,
    display: "flex",
    height: theme.spacing(headerHeight),
    padding: `0 ${theme.spacing(3)}`,
  },
  item: {
    flex: "initial",
    marginRight: "52px",
    [theme.breakpoints.down(768)]: {
      marginRight: "44px",
    },
    ["&:first-of-type"]: {
      marginRight: "26px",
      paddingRight: "26px",
      borderRight: "solid 1px #FFF",
    },
  },
  leftContainer: {
    alignItems: "center",
    display: "flex",
  },
  userMenuContainer: {
    marginLeft: "auto",
  },
}));

const Header = (): JSX.Element => {
  const { classes } = useStyles();
  const headerRef = useRef<HTMLElement>(null);

  return (
    <header className={classes.header} ref={headerRef}>
      <div className={classes.leftContainer}>
        <div className={classes.item}>
          <Poller />
        </div>

        <div className={classes.item}>
          <ServiceStatusCounter />
        </div>

        <div className={classes.item}>
          <HostStatusCounter />
        </div>

        <div className={classes.item}>
          <FederatedComponent path="/bam/header/topCounter" />
        </div>
      </div>

      <div className={classes.userMenuContainer}>
        <UserMenu headerRef={headerRef} />
      </div>
    </header>
  );
};

export default Header;
