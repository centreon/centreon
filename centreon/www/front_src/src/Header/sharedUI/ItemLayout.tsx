import ExpandMoreIcon from "@mui/icons-material/ExpandMore";
import ExpandLessIcon from "@mui/icons-material/ExpandLess";
import { Badge } from "@mui/material";
import { makeStyles } from "tss-react/mui";
import { ClickAwayListener } from "@mui/material";
import { useState, useEffect } from "react";

interface ItemLayoutProps {
  Icon: JSX.Element;
  title: string;
  testId: string;
  renderIndicators: () => JSX.Element;
  renderSubMenu: (params: { closeSubMenu: () => void }) => JSX.Element;
  toggled: boolean;
  toggleSubMenu: () => void;
  showPendingBadge: boolean;
}

const useStyles = makeStyles()((theme) => ({
  container: {
    position: "relative",
  },
  header: {
    [theme.breakpoints.down(768)]: {
      display: "flex",
      flexFlow: "row no-wrap",
    },
  },
  button: {
    appearance: "none",
    border: 0,
    background: "none",
    display: "flex",
    cursor: "pointer",
    padding: "0",
    color: theme.palette.common.white,

    [theme.breakpoints.up(768)]: {
      flexFlow: "row no-wrap",
      marginTop: "4px",
      alignItems: "center",
    },

    [theme.breakpoints.down(768)]: {
      order: 1,
      flexFlow: "column wrap",
      alignItems: "center",
    },

    ["& > svg"]: {
      margin: "-2px",
      height: "0.9em",

      [theme.breakpoints.down(768)]: {
        margin: "-4px",
      },
    },
  },
  iconWrapper: {
    [theme.breakpoints.up(768)]: {
      position: "absolute",
      top: "0",
    },
  },
  textWrapper: {
    flex: "100%",
    display: "inline-flex",
    alignItems: "center",
    fontSize: theme.typography.body2.fontSize,
    lineHeight: "1",
    whiteSpace: "nowrap",
    [theme.breakpoints.down(768)]: {
      display: "none",
    },
  },
  indicators: {
    [theme.breakpoints.down(600)]: {
      display: "none",
    },
    [theme.breakpoints.down(768)]: {
      flex: "initial",
      order: 2,
      marginLeft: theme.spacing(0.5),
    },
    [theme.breakpoints.up(768)]: {
      marginLeft: theme.spacing(3.75),
      height: theme.spacing(2.5),
    },
  },
  subMenu: {
    backgroundColor: theme.palette.background.default,
    boxShadow: theme.shadows[3],
    boxSizing: "border-box",
    left: 0,
    visibility: "hidden",
    position: "absolute",
    textAlign: "left",
    top: "calc(100% + 10px)",
    width: theme.spacing(20),
    zIndex: theme.zIndex.mobileStepper,
  },
  subMenuOpen: {
    visibility: "visible",
  },
}));

export default ({
  Icon,
  title,
  testId,
  renderIndicators,
  renderSubMenu,
  showPendingBadge,
}: ItemLayoutProps) => {
  const { classes, cx } = useStyles();
  const [toggled, setToggled] = useState(false);

  useEffect(() => {
    const closeMenu = () => setToggled(false);

    if (toggled) {
      window.addEventListener("locationchange", closeMenu);
    }

    return () => {
      window.removeEventListener("locationchange", closeMenu);
    };
  }, [toggled]);

  return (
    <ClickAwayListener
      onClickAway={() => {
        if (toggled) setToggled(!toggled);
      }}
    >
      <div className={classes.container} data-testid={`${testId}-container`}>
        <div className={classes.header}>
          <div
            className={classes.indicators}
            data-testid={`${testId}-indicators`}
          >
            {renderIndicators()}
          </div>
          <button
            className={classes.button}
            onClick={() => setToggled(!toggled)}
            data-testid={`${testId}-button`}
          >
            <span className={classes.iconWrapper}>
              <Badge
                anchorOrigin={{ horizontal: "right", vertical: "top" }}
                color="pending"
                invisible={!showPendingBadge}
                overlap="circular"
                variant="dot"
              >
                <Icon />
              </Badge>
            </span>
            <span className={classes.textWrapper}>{title}</span>
            {toggled ? <ExpandMoreIcon /> : <ExpandLessIcon />}
          </button>
        </div>
        <div
          className={cx(classes.subMenu, { [classes.subMenuOpen]: toggled })}
          data-testid={`${testId}-sub-menu`}
        >
          {renderSubMenu({ closeSubMenu: () => setToggled(false) })}
        </div>
      </div>
    </ClickAwayListener>
  );
};
