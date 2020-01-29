import { styled } from '@material-ui/core/styles';

function RoundedInvertedIcon(Icon) {
  return styled(Icon)(() => ({
    color: '#fff',
    backgroundColor: '#707070',
    borderRadius: '50%',
    MozBoxSizing: 'border-box',
    WebkitBoxSizing: 'border-box',
    boxSizing: 'border-box',
    padding: 4,
  }));
}

export default RoundedInvertedIcon;
