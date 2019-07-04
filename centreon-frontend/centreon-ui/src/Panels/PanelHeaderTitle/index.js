import React from 'react';
import classnames from 'classnames';
import styles from './panel-header-title.scss';

class PanelHeaderTitle extends React.Component {
  render() {
    const { label } = this.props;
    return (
      <h3 className={classnames(styles['panel-header-title'])}>{label}</h3>
    );
  }
}

export default PanelHeaderTitle;
