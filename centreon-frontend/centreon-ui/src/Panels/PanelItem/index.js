import React from "react";
import classnames from "classnames";
import styles from "./panel-item.scss";

class PanelItem extends React.Component {
  render() {
    const {
      panelItemType,
      children,
      panelItemShow,
      panelItemMargin,
      panelItemFirst
    } = this.props;
    return (
      <div
        className={classnames(
          styles["panel-item"],
          styles[panelItemFirst],
          styles[panelItemShow],
          styles[panelItemMargin],
          panelItemType
            ? { [styles[`panel-item-${panelItemType}`]]: true }
            : null
        )}
      >
        {children}
      </div>
    );
  }
}

export default PanelItem;
