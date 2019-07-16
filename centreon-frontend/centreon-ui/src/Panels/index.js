/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/jsx-no-bind */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from "react";
import ReactDOM from "react-dom";
import classnames from "classnames";
import Button from "@material-ui/core/Button";
import ArrowForward from "@material-ui/icons/ArrowForwardIos";
import ArrowBack from "@material-ui/icons/ArrowBackIos";
import styles from "./panels.scss";
import styles2 from "./PanelItem/panel-item.scss";
import PanelItem from "./PanelItem";
import PanelHeaderTitle from "./PanelHeaderTitle";
import IconPowerSettings from "../MaterialComponents/Icons/IconPowerSettings";
import IconPowerSettingsDisable from "../MaterialComponents/Icons/IconPowerSettingsDisable";
import IconAttach from "../MaterialComponents/Icons/IconAttach";
import BAForm from "../Forms/BAForm";
import IconCloseNew from "../MaterialComponents/Icons/IconClose";
import InputField from "../InputField";
import TableDefault from "../Table/TableDefault";
import MultiSelectPanel from "../MultiSelectPanel";
import BAModel from "../Mocks/oneBa.json";

class BAPanel extends React.Component {
  state = {
    multiselectActive: false,
    activeMultiselectKey: "",
    nameEditingToggled: false
  };

  toggleSecondPanel = () => {
    const { multiselectActive } = this.state;
    this.setState({
      multiselectActive: !multiselectActive
    });
  };

  toggleNameEditing = () => {
    const { nameEditingToggled } = this.state;
    this.setState({
      nameEditingToggled: !nameEditingToggled
    });
  };

  focusNameEditInput(component) {
    if (component) {
      ReactDOM.findDOMNode(component).focus();
    }
  }

  render() {
    const {
      customClass,
      active,
      onSave,
      onIconClick,
      onToggleClick,
      onClose,
      values = BAModel.result,
      errors,
      multiselectsConfiguration
    } = this.props;
    const { multiselectActive, nameEditingToggled } = this.state;
    return (
      <div
        className={classnames(
          styles.panels,
          styles[customClass || ""],
          styles[active ? "panels-active" : ""],
          styles[multiselectActive ? "panels-second-active" : ""]
        )}
      >
        <div className={classnames(styles["panels-dialog"])}>
          <div className={classnames(styles["panels-inner"])}>
            <div className={classnames(styles["panels-header"])}>
              {values.icon ? (
                <IconAttach onClick={onIconClick} />
              ) : (
                <IconAttach onClick={onIconClick} />
              )}
              {values.activate ? (
                <IconPowerSettings />
              ) : (
                <IconPowerSettingsDisable />
              )}

              {nameEditingToggled ? (
                <InputField
                  placeholder="Click here to add name"
                  type="text"
                  name="name"
                  value={values.name}
                  inputSize={"header"}
                  onBlur={this.toggleNameEditing}
                  reference={this.focusNameEditInput}
                />
              ) : (
                <PanelHeaderTitle
                  label={values.name ? values.name : "Click here to add name"}
                  onClick={this.toggleNameEditing}
                />
              )}

              <IconCloseNew onClick={onClose} />
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
                onClick={onSave}
              >
                Save
              </Button>
            </div>
            <div className={classnames(styles["panels-body"])}>
              <PanelItem
                panelItemType="big"
                panelItemShow={multiselectActive ? "panel-item-show-big" : ""}
              >
                <div className={classnames(styles2["panel-item-inner"])}>
                  <BAForm
                    values={values}
                    errors={{}}
                    multiselectsConfiguration={{}}
                  />
                </div>
                <span
                  className={classnames(
                    styles["panels-arrow"],
                    multiselectActive ? styles["panels-arrow-right"] : ""
                  )}
                  onClick={this.toggleSecondPanel.bind(this)}
                >
                  {multiselectActive ? <ArrowForward /> : null}
                </span>
              </PanelItem>
              <MultiSelectPanel
                styles={styles2}
                active={multiselectActive}
                title={""}
                data={[]}
                tableConfiguration={[]}
                onSearch={() => {}}
                onPaginate={() => {}}
                onPaginationLimitChanged={() => {}}
                onSort={() => {}}
                currentPage={0}
                totalRows={150}
                currentlySelected={[]}
                paginationLimit={5}
              />
            </div>
          </div>
        </div>
      </div>
    );
  }
}

export default BAPanel;
