import { JsonDecoder } from 'ts.data.json';

import { DashboardDetails, PanelDetails } from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

export const dashboardDetailsDecoder = JsonDecoder.object<DashboardDetails>(
  {
    ...namedEntityDecoder,
    createdAt: JsonDecoder.string,
    createdBy: JsonDecoder.object<DashboardDetails['createdBy']>(
      namedEntityDecoder,
      'CreatedBy By'
    ),
    description: JsonDecoder.nullable(JsonDecoder.string),
    panels: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Panel'),
      'Panels'
    ),
    updatedAt: JsonDecoder.string,
    updatedBy: JsonDecoder.object<DashboardDetails['updatedBy']>(
      namedEntityDecoder,
      'Updated By'
    )
  },
  'Dashboard Details',
  {
    createdAt: 'created_at',
    createdBy: 'created_by',
    updatedAt: 'updated_at',
    updatedBy: 'updated_by'
  }
);

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

export const panelsDetailsDecoder = JsonDecoder.array(
  panelDetailsDecoder,
  'Panels Details'
);
