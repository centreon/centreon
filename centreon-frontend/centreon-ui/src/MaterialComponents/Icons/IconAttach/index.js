import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Attach from '@material-ui/icons/AttachFile';

const useStyles = makeStyles((theme) => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#707070',
    cursor: 'pointer',
    fontSize: 14,
    verticalAlign: 'middle',
  },
  iconAttach: {
    width: 80,
    height: 49,
    textAlign: 'center',
    lineHeight: '49px',
    backgroundColor: '#fff',
    display: 'inline-block',
    marginRight: 15,
    verticalAlign: 'middle',
    cursor: 'pointer',
    marginLeft: -20,
  },
  iconAttachLabel: {
    fontSize: 12,
    color: '#707070',
    display: 'inline-block',
    verticalAlign: 'middle',
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    marginLeft: 3,
  },
}));

function IconAttach({ customStyle, onClick }) {
  const classes = useStyles();

  return (
    <React.Fragment>
      <span onClick={onClick} className={classes.iconAttach}>
        <Attach style={customStyle} className={classes.icon} />
        <span className={classes.iconAttachLabel}>ICON</span>
      </span>
    </React.Fragment>
  );
}

export default IconAttach;
