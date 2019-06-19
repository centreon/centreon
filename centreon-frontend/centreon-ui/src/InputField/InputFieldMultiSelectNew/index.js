import React from "react";
import classnames from "classnames";
import styles from "./input-field-multi-select.scss";
import InputFieldMultiSelectEmpty from "../../InputField/InputFieldMultiSelectEmpty";

const InputFieldMultiSelect = ({
  type,
  placeholder,
  name,
  error,
  multiSelectType,
  isEmpty,
  ...rest
}) => {
  return (
    <React.Fragment>
      {isEmpty ? (
        <InputFieldMultiSelectEmpty />
      ) : (
        <div
          className={classnames(
            styles["multi-select"],
            multiSelectType ? styles["multi-select-empty"] : ""
          )}
        >
          {!multiSelectType && (
            <input
              name={name}
              type={type}
              placeholder={placeholder}
              className={classnames(styles["multi-select-input"])}
              {...rest}
            />
          )}
          {error ? (
            <div className={classnames(styles["form-error"])}>{error}</div>
          ) : null}
        </div>
      )}
    </React.Fragment>
  );
};

export { InputFieldMultiSelect };

export default InputFieldMultiSelect;
