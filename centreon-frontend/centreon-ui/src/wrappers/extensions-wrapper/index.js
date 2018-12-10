import React, {Component} from 'react';
import '../../global-sass-files/_containers.scss';

class ExtensionsWrapper extends Component {
  render() {
    return (
      <div className="content-wrapper">
        {children}
      </div>
    );
  }
}

export default ExtensionsWrapper;