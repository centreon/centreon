import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import WidgetCheckboxes from './Chekboxes';

const checkboxes = {
  defaultValue: [],
  label: 'Title',
  propertyName: 'checkbox',
  secondaryLabel: 'Secondary label'
};

export const Default = createWidgetPropertyStory(WidgetCheckboxes, {
  ...checkboxes,
  options: [
    { id: 'a', name: 'A' },
    { id: 'b', name: 'B' }
  ]
});

export const WithoutOptions = createWidgetPropertyStory(
  WidgetCheckboxes,
  checkboxes
);
