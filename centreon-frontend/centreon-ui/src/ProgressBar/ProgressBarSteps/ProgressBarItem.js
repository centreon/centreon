import React from 'react';

const ProgressBarItem = ({classActive, number}) => {
  return (
    <li className="progress-bar-item">
      <span className={`progress-bar-link ${classActive}`}>
        {number}
      </span>
    </li>
  )
}

export default ProgressBarItem;