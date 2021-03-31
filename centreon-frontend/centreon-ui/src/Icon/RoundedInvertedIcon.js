import { styled } from '@material-ui/core/styles';

const RoundedInvertedIcon = (Icon) =>
  styled(Icon)(() => ({
    MozBoxSizing: 'border-box',
    WebkitBoxSizing: 'border-box',
    backgroundColor: '#707070',
    borderRadius: '50%',
    boxSizing: 'border-box',
    color: '#fff',
    padding: 4,
  }));

export default RoundedInvertedIcon;
