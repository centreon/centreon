import React, { Component } from "react";
import MultiSelectHolder from "./";
import CustomRow from "../Custom/CustomRow";
import CustomColumn from "../Custom/CustomColumn";
import InputFieldMultiSelectValue from "../InputField/InputFieldMultiSelectValue";

const excludeAfterIndex = 5;

class MultiSelectContainer extends Component {
  render() {
    const {
      label,
      selected,
      error,
      values = [],
      onEdit,
      labelKey = "name",
      valueKey = "id",
      options = []
    } = this.props;
    return (
      <MultiSelectHolder
        isEmpty={values.length == 0}
        multiSelectLabel={label}
        multiSelectCount={values.length.toString()}
        error={error}
        onClick={onEdit}
        selected={selected}
      >
        <CustomRow additionalStyles={["mb-0"]}>
          {values.map((item, index) => {
            let result = null;
            if (index < excludeAfterIndex) {
              for (let i = 0; i < options.length; i++) {
                if (options[i][valueKey] == item) {
                  result = (
                    <CustomColumn customColumn="md-6">
                      <InputFieldMultiSelectValue
                        disabled
                        placeholder={options[i][labelKey]}
                      />
                    </CustomColumn>
                  );
                }
              }
            }
            return result;
          })}
          {values.length > 5 ? (
            <CustomColumn customColumn="md-6">
              <InputFieldMultiSelectNew multiSelectType />
            </CustomColumn>
          ) : null}
        </CustomRow>
      </MultiSelectHolder>
    );
  }
}

export default MultiSelectContainer;
