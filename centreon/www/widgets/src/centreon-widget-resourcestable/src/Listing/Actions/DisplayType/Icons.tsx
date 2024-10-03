import parse from 'html-react-parser';
import { equals } from 'ramda';

import { SvgIcon, useTheme } from '@mui/material';

import { DisplayType } from '../../models';

interface Props {
  displayType: DisplayType;
  isActive: boolean;
}

const getViewByService = (color, backgroundColor): string =>
  `<svg width="24" height="24" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="${backgroundColor}"/><g transform="translate(1 2)"><circle id="Ellipse_192" cx="2" cy="2" r="2" transform="translate(2 2)" fill="${color}"/><path id="Tracé_2976" d="M-2.979,0h16" transform="translate(6.979 4)" fill="none" stroke="${color}" stroke-width="2"/></g><g transform="translate(1 2)"><circle id="Ellipse_193" cx="2" cy="2" r="2" transform="translate(2 8)" fill="${color}"/><path id="Tracé_2977" d="M-2.979,0h16" transform="translate(6.979 10)" fill="none" stroke="${color}" stroke-width="2"/></g><g transform="translate(1 3)"><circle id="Ellipse_194" cx="2" cy="2" r="2" transform="translate(2 13)" fill="${color}"/><path id="Tracé_2978" d="M-2.979,0h16" transform="translate(6.979 15)" fill="none" stroke="${color}" stroke-width="2"/></g></svg>`;

const getViewByHost = (color, backgroundColor): string =>
  `<svg width="24" height="24" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="${backgroundColor}"/><circle cx="3.5" cy="3.5" r="3.5" transform="translate(2 3)" fill="${color}"/><line x2="10" transform="translate(12 20)" fill="none" stroke="${color}" stroke-width="2"/><line x2="10" transform="translate(12 16)" fill="none" stroke="${color}" stroke-width="2"/><line x2="10" transform="translate(12 12)" fill="none" stroke="${color}" stroke-width="2"/><path d="M8.829,6.5h3.317V21" fill="none" stroke="${color}" stroke-width="2"/></svg>`;

const getViewByAll = (color, backgroundColor): string =>
  `<svg width="24" height="24" viewBox="0 0 24 24"><rect width="24" height="24" rx="4" fill="${backgroundColor}" /><path d="M6.964-5.178H2.627L1.654-2.443H.246L4.2-12.924H5.393L9.352-2.443h-1.4ZM3.04-6.316H6.558L4.8-11.218Zm9.19,3.873H10.915V-13.5H12.23Zm3.795,0H14.71V-13.5h1.315Z" transform="translate(3.865 19.5)" fill="${color}"/></svg>`;

const DisplayTypeIcon = ({ displayType, isActive }: Props): JSX.Element => {
  const { palette } = useTheme();

  const color = isActive ? palette.common.white : palette.primary.main;
  const backgroundColor = isActive ? palette.primary.main : 'none';

  if (equals(displayType, DisplayType.Host)) {
    return (
      <SvgIcon height="24" viewBox="0 0 24 24" width="24">
        {parse(getViewByHost(color, backgroundColor))}
      </SvgIcon>
    );
  }

  if (equals(displayType, DisplayType.Service)) {
    return (
      <SvgIcon height="24" viewBox="0 0 24 24" width="24">
        {parse(getViewByService(color, backgroundColor))}
      </SvgIcon>
    );
  }

  return (
    <SvgIcon height="24" viewBox="0 0 24 24" width="24">
      {parse(getViewByAll(color, backgroundColor))}
    </SvgIcon>
  );
};

export default DisplayTypeIcon;
