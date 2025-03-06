import parse from 'html-react-parser';

import { SvgIcon } from '@mui/material';

const getIcon = (type, color): string =>
  `<svg viewBox="0 0 24 24"><g id="Calque_1-2" data-name="Calque 1"><g><rect fill="none" width="24" height="23.83"/></g><g><polygon fill="none" points="12.13 10.8 10.81 12.11 11.8 13.1 13.12 11.79 12.13 10.8"/><polygon fill="none" points="9.9 8.59 8.59 9.9 9.57 10.89 10.89 9.57 9.9 8.59"/><path fill=${color} d="M16.91,11.92c.87,0,1.67.24,2.38.63l1.92-1.9c.73-.72.87-1.75.33-2.29l-1.98-1.97c-.73.72-1.76.87-2.31.33-.55-.54-.4-1.57.33-2.29l-1.98-1.97c-.55-.54-1.58-.4-2.31.33L2.73,13.26c-.73.72-.87,1.74-.33,2.28h0s1.98,1.97,1.98,1.97c.73-.72,1.76-.87,2.31-.32.54.54.39,1.57-.34,2.29l1.98,1.97c.55.54,1.58.39,2.31-.33l1.89-1.88c-.39-.71-.63-1.51-.63-2.37,0-2.74,2.24-4.97,5-4.97ZM9.57,10.89l-.99-.98,1.32-1.31.99.98-1.32,1.31ZM10.81,12.11l1.32-1.31.99.98-1.32,1.31-.99-.98Z"/></g><text fill=${color} font-family="Roboto-Bold, Roboto" font-size="9.93px" font-weight="700" transform="translate(13.32 21.31)"><tspan x="0" y="0">${type}</tspan></text></g></svg>`;

const OpenTicket = ({
  type,
  color
}: {
  color: string;
  type: 'H' | 'S';
}): JSX.Element => (
  <SvgIcon height="24" viewBox="0 0 24 24" width="24">
    {parse(getIcon(type, color))}
  </SvgIcon>
);

export default OpenTicket;
