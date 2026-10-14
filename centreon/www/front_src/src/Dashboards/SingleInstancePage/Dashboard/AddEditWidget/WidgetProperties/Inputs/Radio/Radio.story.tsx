import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import WidgetRadio from './Radio';

const radio = {
  defaultValue: [],
  label: 'Title',
  propertyName: 'radio'
};

export const Default = createWidgetPropertyStory(WidgetRadio, {
  ...radio,
  options: [
    { id: 'a', name: 'A' },
    { id: 'b', name: 'B' }
  ]
});

export const WithoutOptions = createWidgetPropertyStory(WidgetRadio, radio);
