import styled from '@emotion/styled';

function RoundedInvertedIcon(Icon) {
  return styled(Icon)(() => ({
    color: '#fff',
    backgroundColor: '#707070',
    borderRadius: '50%',
    MozBoxSizing: 'border-box',
    WebkitBoxSizing: 'border-box',
    boxSizing: 'border-box',
    padding: 3,
  }));
}

export default RoundedInvertedIcon;
