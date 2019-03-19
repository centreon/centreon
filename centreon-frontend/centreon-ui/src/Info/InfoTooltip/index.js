import React, {Component} from 'react';
import IconInfo from '../../Icon/IconInfo';
import './info-tooltip.scss';

class Tooltip extends Component {
  render() { 
    const {iconText, tooltipText, iconColor} = this.props;
    return ( 
      <div class="tooltip"><IconInfo iconName="question" iconText={iconText} iconColor={iconColor} />
        <span class="tooltiptext">{tooltipText}</span>
      </div>
    );
  }
}

export default Tooltip;