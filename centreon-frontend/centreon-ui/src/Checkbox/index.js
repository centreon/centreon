import React from "react";
import './checkbox.scss';
import classnames from 'classnames';
import styles from './checkbox.scss';

const Checkbox = ({
  iconColor,
  checked,
  label,
  value,
  info,
  name,
  ...rest
}) => {
  const cnCustomControl = classnames(styles["custom-control"], {[styles["custom-checkbox"]]: true}, styles[iconColor ? iconColor : '']);
  return (
    <div className={classnames(styles["form-group"])}>
      <div className={cnCustomControl}>
        <input
          name={name}
          aria-checked={checked}
          checked={checked}
          className={classnames(styles["custom-control-input"])}
          type="checkbox"
        />
        <label htmlFor={rest.id} className={classnames(styles["custom-control-label"])}>
          {label}
          {info}
        </label>
      </div>
    </div>
  )
};

export { Checkbox };

export default Checkbox;
