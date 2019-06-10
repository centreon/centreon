import React from 'react';
import classnames from "classnames";
import styles from "./panel-item.scss";

const PanelItem = ({panelItemType, children, panelItemShow, panelItemMargin}) => {
  return (
    <div className={classnames(styles["panel-item"], styles[panelItemShow], styles[panelItemMargin], panelItemType ? {[styles[`panel-item-${panelItemType}`]]: true} : null)}>
      {children}
    </div>
  )
}

export default PanelItem;