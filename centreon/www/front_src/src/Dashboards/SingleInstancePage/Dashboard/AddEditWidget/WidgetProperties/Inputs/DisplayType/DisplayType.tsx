import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import Subtitle from '../../../../components/Subtitle';
import { labelDisplayAs } from '../../../../translatedLabels';
import { WidgetPropertyProps } from '../../../models';
import { useResourceStyles } from '../Inputs.styles';

import { useStyles } from './DisplayType.styles';
import Option from './Option';
import useDisplayType from './useDisplayType';

const DisplayType = ({
  options,
  propertyName,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();
  const { classes: resourcesClasses } = useResourceStyles();

  const { t } = useTranslation();

  const { value, changeType } = useDisplayType({ propertyName });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div>
      <Label className={resourcesClasses.subtitle}>{t(labelDisplayAs)}</Label>
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
