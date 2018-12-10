import React, {Component} from 'react';
import '../../global-sass-files/_grid.scss';

class ExtensionsWrapper extends Component {
  render() {
    const {containerBackground} = this.props;
    return (
      <div className={`container container-${containerBackground}`}>
        <div className="content-wrapper">
          {children}
        </div>
      </div>
    );
  }
}

export default ExtensionsWrapper;