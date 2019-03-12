import React, {Component} from 'react';
import IconInfo from '../../Icon/IconInfo';
import './info-tooltip.scss';

class Tooltip extends Component {
  render() { 
    return ( 
      <div class="tooltip"><IconInfo iconName="question" />
        <span class="tooltiptext">Tooltip text</span>
      </div>
    );
  }
}

export default Tooltip;
 