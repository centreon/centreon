import { useTranslation } from 'react-i18next';

import { labelDisplayAs } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import Subtitle from '../../../../components/Subtitle';

import useDisplayType from './useDisplayType';
import Option from './Option';
import { useStyles } from './DisplayType.styles';

const DisplayType = ({
  options,
  propertyName
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { value, changeType } = useDisplayType({ propertyName });

  return (
    <div>
      <Subtitle>{t(labelDisplayAs)}</Subtitle>
      <div className={classes.displayTypeContainer}>
        {options?.map(({ id, icon, label }) => (
          <Option
            changeType={changeType}
            icon={icon}
            key={id}
            label={label}
            type={id}
            value={value}
          />
        ))}
      </div>
    </div>
  );
};

export default DisplayType;
