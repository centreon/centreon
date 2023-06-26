import { JsonDecoder } from 'ts.data.json';

import { DashboardDetails, PanelDetails } from '../models';
import { dashboardDecoderObject } from '../../api/decoders';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const panelDetailsDecoder = JsonDecoder.object<PanelDetails>(
  {
    ...namedEntityDecoder,
    layout: JsonDecoder.object<PanelDetails['layout']>(
      {
        height: JsonDecoder.number,
        minHeight: JsonDecoder.number,
        minWidth: JsonDecoder.number,
        width: JsonDecoder.number,
        x: JsonDecoder.number,
        y: JsonDecoder.number
      },
      'Layout',
      {
        minHeight: 'min_height',
        minWidth: 'min_width'
      }
    ),
    widgetSettings: JsonDecoder.succeed,
    widgetType: JsonDecoder.string
  },
  'Panel Details',
  {
    widgetSettings: 'widget_settings',
    widgetType: 'widget_type'
  }
);

const panelsDetailsDecoder = JsonDecoder.array(
  panelDetailsDecoder,
  'Panels Details'
);

export const dashboardDetailsDecoder = JsonDecoder.object<DashboardDetails>(
  {
    ...dashboardDecoderObject,
    panels: panelsDetailsDecoder
  },
  'Dashboard Details',
  {
    createdAt: 'created_at',
    createdBy: 'created_by',
    ownRole: 'own_role',
    updatedAt: 'updated_at',
    updatedBy: 'updated_by'
  }
);
