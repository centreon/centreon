import React from 'react';
import classnames from "classnames";
import styles from "./panel-item.scss";

const PanelItem = ({panelItemType, children, panelItemShow, panelItemMargin, panelItemFirst}) => {
  return (
    <div className={classnames(styles["panel-item"], styles[panelItemFirst], styles[panelItemShow], styles[panelItemMargin], panelItemType ? {[styles[`panel-item-${panelItemType}`]]: true} : null)}>
      {children}
    </div>
  )
}

export default PanelItem;