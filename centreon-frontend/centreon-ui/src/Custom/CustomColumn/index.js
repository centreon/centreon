import React, { Component } from 'react';
import classnames from 'classnames';
import styles from '../../global-sass-files/_grid.scss';

class CustomColumn extends Component{
  render(){
    const {children, customColumn, additionalStyles} = this.props;
    let additionalClasses = [];
    if(additionalStyles){
      for(let i = 0; i < additionalStyles.length;i++){
        additionalClasses.push(styles[additionalStyles[i]])
      }
    }
    
    return (
      <div className={classnames({[styles[`container__col-${customColumn}`]]: true}, additionalClasses)}>
        {children}
      </div>
    );
  }
};

export default CustomColumn;