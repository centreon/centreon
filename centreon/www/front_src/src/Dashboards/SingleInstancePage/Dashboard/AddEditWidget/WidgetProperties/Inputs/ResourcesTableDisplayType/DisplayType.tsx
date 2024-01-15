import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';

import { Box } from '@mui/material';

import { Widget, WidgetPropertyProps } from '../../../models';
import { editProperties } from '../../../../hooks/useCanEditDashboard';
import { getProperty } from '../utils';
import Subtitle from '../../../../components/Subtitle';

import Action from './Action';
import { useStyles } from './DisplayType.styles';
import { getIconbyView } from './icons/getIconByView';

const DisplayType = ({
  propertyName,
  options,
  label,
  defaultValue
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const { canEditField } = editProperties.useCanEditProperties();

  const change = (displayType): void => {
    setFieldTouched(`options.${propertyName}`, true);
    setFieldValue(`options.${propertyName}`, displayType);
  };

  const optionsToDisplay = options?.map((option) => ({
    ...option,
    iconPath: getIconbyView(option?.id)
  }));

  return (
    <div>
      <Subtitle>{t(label)}</Subtitle>
      <Box className={classes.items} data-testid="tree view">
        {optionsToDisplay.map(({ id, name, iconPath }) => {
          return (
            <Action
              disabled={!canEditField}
              displayType={value || defaultValue}
              iconPath={iconPath}
              id={id}
              key={name}
              name={name}
              selectDisplayType={() => change(id)}
            />
          );
        })}
      </Box>
    </div>
  );
};

export default DisplayType;
