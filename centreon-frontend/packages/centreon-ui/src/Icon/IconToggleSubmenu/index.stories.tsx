import Icon from '.';

export default { title: 'Icon/ToggleSubmenu' };

export const arrow = (): JSX.Element => (
  <div
    style={{
      backgroundColor: '#232f39',
      padding: '10px',
    }}
  >
    <Icon rotate={false} onClick={(): void => undefined} />
  </div>
);
