import React from "react";
import classnames from "classnames";
import Button from "@material-ui/core/Button";
import ArrowForward from "@material-ui/icons/ArrowForwardIos";
import ArrowBack from "@material-ui/icons/ArrowBackIos";
import styles from "./panels.scss";
import styles2 from "./PanelItem/panel-item.scss";
import PanelItem from "./PanelItem";
import PanelHeaderTitle from "./PanelHeaderTitle";
import IconPowerSettings from "../MaterialComponents/Icons/IconPowerSettings";
import IconAttach from "../MaterialComponents/Icons/IconAttach";
import Accordion from "../MaterialComponents/Accordion";
import IconCloseNew from "../MaterialComponents/Icons/IconClose";
import InputFieldSearch from "../InputField/InputFieldSearch";
import TableDefault from "../Table/TableDefault";
import ButtonCustom from "../Button/ButtonCustom";

class Panels extends React.Component {
  state = {
    panelItemActive: false
  };

  togglePanel = () => {
    this.setState({
      panelItemActive: false
    });
  };

  toggleSecondPanel = () => {
    const { panelItemActive } = this.state;
    this.setState({
      panelItemActive: !panelItemActive
    });
  };

  render() {
    const { customClass, togglePanel } = this.props;
    const { panelItemActive  } = this.state;
    return (
        <div
          className={classnames(
            styles.panels,
            styles[customClass ? customClass : ""],
            styles[togglePanel ? "panels-active" : ""],
            styles[panelItemActive ? "panels-second-active" : ""]
          )}
        >
          <div className={classnames(styles["panels-dialog"])}>
            <div className={classnames(styles["panels-inner"])}>
              <div className={classnames(styles["panels-header"])}>
                <IconAttach />
                <IconPowerSettings active={true} />
                <PanelHeaderTitle label="Africa office availability" />
                <IconCloseNew onClick={this.togglePanel.bind(this)} />
                <Button
                  variant="contained"
                  color="primary"
                  style={{
                    position: "absolute",
                    right: 60,
                    top: 9,
                    backgroundColor: "#0072CE",
                    fontSize: 11
                  }}
                >
                  Save
                </Button>
              </div>
              <div className={classnames(styles["panels-body"])}>
                <PanelItem
                  panelItemType="big"
                  panelItemShow={panelItemActive ? "panel-item-show-big" : ""}
                >
                  <div className={classnames(styles2["panel-item-inner"])}>
                    <Accordion />
                  </div>
                  <span
                    className={classnames(
                      styles["panels-arrow"],
                      panelItemActive ? styles["panels-arrow-right"] : ""
                    )}
                    onClick={this.toggleSecondPanel.bind(this)}
                  >
                    {panelItemActive ? <ArrowForward /> : <ArrowBack />}
                  </span>
                </PanelItem>
                <PanelItem
                  panelItemType="small"
                  panelItemShow={panelItemActive ? "panel-item-show" : ""}
                >
                  <div className={classnames(styles2["panel-item-inner"])}>
                    <h3 className={classnames(styles2["panel-item-title"])}>
                      Manage Business View
                    </h3>
                    <InputFieldSearch
                      style={{ width: "100%", marginBottom: 15, boxSizing: 'border-box' }}
                    />
                    <TableDefault style={{minWidth: 'auto'}} />
                  </div>
                </PanelItem>
              </div>
            </div>
          </div>
        </div>
    );
  }
}

export default Panels;
