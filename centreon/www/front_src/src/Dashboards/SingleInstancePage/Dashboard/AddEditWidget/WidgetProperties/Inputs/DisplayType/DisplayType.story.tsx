import { createWidgetPropertyStory } from '../../WidgetPropertyStoryFrame';
import DisplayType from './DisplayType';

const options = [
  {
    icon: '<svg viewBox="0 0 24 24"><path d="M5 4v3h5.5v12h3V7H19V4z"></path></svg>',
    id: 'text',
    label: 'Text'
  },
  {
    icon: '<svg viewBox="0 0 24 24"><path d="m20.38 8.57-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44z"></path></svg>',
    id: 'gauge',
    label: 'Gauge'
  },
  {
    icon: '<svg viewBox="0 0 24 24"><path d="M4 9h4v11H4zm12 4h4v7h-4zm-6-9h4v16h-4z"></path></svg>',
    id: 'bar',
    label: 'Bar'
  }
];

export const Default = createWidgetPropertyStory(DisplayType, {
  // DisplayType reads label and icon, not the SelectEntry declared by WidgetPropertyProps.
  options: options as never,
  propertyName: 'displayType'
});
