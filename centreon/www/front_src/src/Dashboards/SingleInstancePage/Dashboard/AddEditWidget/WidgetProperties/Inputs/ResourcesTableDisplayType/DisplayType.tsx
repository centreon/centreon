import { useTranslation } from 'react-i18next';

import {
  labelDisplayType,
  labelViewByHost,
  labelViewByService,
  labelAll
} from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';

import useDisplayType from './useDisplayType';
import Option from './Option';
import { useStyles } from './DisplayType.styles';

export const options = [
  {
    icon: '<svg id="view_all" data-name="view all" xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24"><rect id="Rectangle_1650" data-name="Rectangle 1650" width="24" height="24" fill="none"/><path id="Label_menu" data-name="Label menu" d="M6.964-5.178H2.627L1.654-2.443H.246L4.2-12.924H5.393L9.352-2.443h-1.4ZM3.04-6.316H6.558L4.8-11.218Zm9.19,3.873H10.915V-13.5H12.23Zm3.795,0H14.71V-13.5h1.315Z" transform="translate(3.865 19.5)" fill="#A2A2A2"/></svg>',
    label: labelAll,
    type: 'all'
  },
  {
    icon: '<svg id="vue_par_host" data-name="vue par host" xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24"><rect id="Rectangle_1650" data-name="Rectangle 1650" width="24" height="24" fill="none"/><circle id="Ellipse_192" data-name="Ellipse 192" cx="3.5" cy="3.5" r="3.5" transform="translate(2 3)" fill="#a2a2a2"/><line id="Ligne_318" data-name="Ligne 318" x2="10" transform="translate(12 20)" fill="none" stroke="#a2a2a2" stroke-width="2"/><line id="Ligne_319" data-name="Ligne 319" x2="10" transform="translate(12 16)" fill="none" stroke="#a2a2a2" stroke-width="2"/><line id="Ligne_320" data-name="Ligne 320" x2="10" transform="translate(12 12)" fill="none" stroke="#a2a2a2" stroke-width="2"/><path id="Tracé_2975" data-name="Tracé 2975" d="M8.829,6.5h3.317V21" fill="none" stroke="#a2a2a2" stroke-width="2"/></svg>',
    label: labelViewByHost,
    type: 'host'
  },
  {
    icon: '<svg id="View_service" data-name="View service" xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24"><rect id="Rectangle_1650" data-name="Rectangle 1650" width="24" height="28" fill="none"/><g id="Groupe_2458" data-name="Groupe 2458" transform="translate(1 2)"><circle id="Ellipse_192" data-name="Ellipse 192" cx="2" cy="2" r="2" transform="translate(2 2)" fill="#a2a2a2"/><path id="Tracé_2976" data-name="Tracé 2976" d="M-2.979,0h16" transform="translate(6.979 4)" fill="none" stroke="#a2a2a2" stroke-width="2"/></g><g id="Groupe_2459" data-name="Groupe 2459" transform="translate(1 2)"><circle id="Ellipse_193" data-name="Ellipse 193" cx="2" cy="2" r="2" transform="translate(2 8)" fill="#a2a2a2"/><path id="Tracé_2977" data-name="Tracé 2977" d="M-2.979,0h16" transform="translate(6.979 10)" fill="none" stroke="#a2a2a2" stroke-width="2"/></g><g id="Groupe_2460" data-name="Groupe 2460" transform="translate(1 3)"><circle id="Ellipse_194" data-name="Ellipse 194" cx="2" cy="2" r="2" transform="translate(2 13)" fill="#a2a2a2"/><path id="Tracé_2978" data-name="Tracé 2978" d="M-2.979,0h16" transform="translate(6.979 15)" fill="none" stroke="#a2a2a2" stroke-width="2"/></g></svg>',
    label: labelViewByService,
    type: 'service'
  }
];

const DisplayType = (props: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const { value, changeType } = useDisplayType(props);

  return (
    <div>
      <Subtitle>{t(labelDisplayType)}</Subtitle>
      <div className={classes.container}>
        {options.map(({ type, icon, label }) => (
          <Option
            changeType={changeType}
            icon={icon}
            key={type}
            label={label}
            type={type}
            value={value}
          />
        ))}
      </div>
    </div>
  );
};

export default DisplayType;
