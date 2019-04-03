import React from "react";
import classnames from 'classnames';
import styles from './input-text.scss';
import IconInfo from '../Icon/IconInfo';

const InputField = ({
  type,
  label,
  placeholder,
  topRightLabel,
  name,
  inputSize,
  error,
  iconName,
  iconColor,
  ...rest
}) => {
  return (
    <div className={classnames(styles["form-group"], styles[inputSize ? inputSize : ''], error ? styles['has-danger'] : '')}>
      {label && <label htmlFor={rest.id}>
        <span>{iconName ? <IconInfo iconName={iconName} iconColor={iconColor}/> : null } {label}</span>
        <span className={classnames(styles["label-option"], styles["required"])}>
          {topRightLabel ? topRightLabel : null}
        </span>
      </label>}
      <input
        name={name}
        type={type}
        placeholder={placeholder}
        className={classnames(styles["form-control"])}
        {...rest}
      />
      {error ? (
        <div className={classnames(styles["form-error"])}>
          {error}
        </div>
      ) : null}
    </div>
  );
};

export { InputField };

export default InputField;