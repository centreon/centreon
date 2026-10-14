import { object } from 'yup';

import { createWidgetPropertyStory } from '../WidgetPropertyStoryFrame';
import Boundaries from './Boundaries';
import { boundariesValidationSchema } from './utils';

export const Default = createWidgetPropertyStory(
  Boundaries,
  { isInGroup: true, propertyName: 'boundaries' },
  object().shape({
    options: object().shape({ boundaries: boundariesValidationSchema })
  })
);
