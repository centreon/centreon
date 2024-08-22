import { useAtomValue } from 'jotai';
import { path, equals, isEmpty, isNil, keys } from 'ramda';
import { useTranslation } from 'react-i18next';

import { CollapsibleItem } from '@centreon/ui/components';

import Subtitle from '../../components/Subtitle';
import {
  labelDescription,
  labelShowDescription,
  labelTitle,
  labelWidgetProperties
} from '../../translatedLabels';
import { widgetPropertiesAtom } from '../atoms';

import CollapsibleWidgetProperties from './CollapsibleWidgetProperties';
import { WidgetRichTextEditor, WidgetSwitch, WidgetTextField } from './Inputs';
import { useWidgetPropertiesStyles } from './widgetProperties.styles';

const WidgetProperties = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWidgetPropertiesStyles();

  const selectedWidgetProperties = useAtomValue(widgetPropertiesAtom);

  const isWidgetSelected = !isNil(selectedWidgetProperties);

  const inputCategories = isWidgetSelected
    ? ['options', ...keys(selectedWidgetProperties?.categories || {})]
    : [];

  return (
    <div className={classes.widgetPropertiesContainer}>
      {isWidgetSelected && (
        <CollapsibleItem defaultExpanded title={t(labelWidgetProperties)}>
          <div className={classes.widgetProperties}>
            <WidgetTextField label={labelTitle} propertyName="name" />
            <div>
              <Subtitle>{t(labelDescription)}</Subtitle>
              <div className={classes.widgetDescription}>
                <WidgetRichTextEditor
                  label={labelDescription}
                  propertyName="description.content"
                />
              </div>
              <WidgetSwitch
                label={labelShowDescription}
                propertyName="description.enabled"
              />
            </div>
          </div>
        </CollapsibleItem>
      )}
      {inputCategories.map((category) => (
        <CollapsibleWidgetProperties
          hasGroups={
            !isEmpty(
              path(
                equals(category, 'options')
                  ? [category, 'groups']
                  : ['categories', category, 'groups'],
                selectedWidgetProperties
              )
            ) &&
            !isNil(
              path(
                equals(category, 'options')
                  ? [category, 'groups']
                  : ['categories', category, 'groups'],
                selectedWidgetProperties
              )
            )
          }
          key={category}
          propertyKey={category}
        />
      ))}
    </div>
  );
};

export default WidgetProperties;
