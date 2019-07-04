import React from 'react';
import classnames from 'classnames';
import styles from './info-state-icon.scss';

const IconInfo = ({ iconName, iconText, iconColor, iconPosition }) => {
  const cn = classnames(
    styles.info,
    { [styles[`info-${iconName}`]]: true },
    styles[iconPosition || ''],
    styles[iconColor || ''],
  );
  return (
    <React.Fragment>
      {iconName && <span className={cn} />}
      {iconText && (
        <span className={classnames(styles['info-text'])}>{iconText}</span>
      )}
    </React.Fragment>
  );
};

export default IconInfo;
