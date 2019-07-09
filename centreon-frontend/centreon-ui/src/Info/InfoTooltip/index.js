/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import classnames from 'classnames';
import styles from './info-tooltip.scss';
import IconInfo from '../../Icon/IconInfo';

class Tooltip extends Component {
  render() {
    const { iconText, tooltipText, iconColor } = this.props;
    return (
      <div className={classnames(styles.tooltip)}>
        <IconInfo
          iconName="question"
          iconText={iconText}
          iconColor={iconColor}
        />
        <span className={classnames(styles.tooltiptext)}>{tooltipText}</span>
      </div>
    );
  }
}

export default Tooltip;
