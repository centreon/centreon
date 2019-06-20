import React from "react";
import classnames from 'classnames';
import styles from './radio-button.scss';

const RadioField = ({ error, label, info, iconColor, ...rest }) => (
  <div className={classnames(styles["custom-control"], styles["custom-radio"], styles["form-group"], styles[iconColor ? iconColor : ''])}>
    <input
      className={classnames(styles["form-check-input"])}
      type="radio"
      info
      {...rest}
    />
    <label htmlFor={rest.id} className={classnames(styles["custom-control-label"])}>
      {label}
      {info}
    </label>
    {error ? (
      <div className={classnames(styles["invalid-feedback"])}>
        <i className={classnames(styles["fas"], styles["fa-exclamation-triangle"])}/>
        <div className={classnames(styles["field__msg"], styles["field__msg--error"])}>{error}</div>
      </div>
    ) : null}
  </div>
);

export { RadioField };

export default RadioField;