import { SelectEntry } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { AgentType, ConnectionMode } from './models';
import { labelCMA, labelNoTLS, labelTLS } from './translatedLabels';

export const filtersInitialValues = {
  name: '',
  'poller.id': [],
  type: []
};

export const defaultSelectedColumnIds = ['name', 'type', 'pollers', 'actions'];

export const agentTypes: Array<SelectEntry> = [
  { id: AgentType.Telegraf, name: capitalize(AgentType.Telegraf) },
  { id: AgentType.CMA, name: labelCMA }
];

export const connectionModes: Array<SelectEntry> = [
  { id: ConnectionMode.secure, name: labelTLS },
  { id: ConnectionMode.noTLS, name: labelNoTLS }
];

export const agentTypeOptions = [
  {
    id: AgentType.Telegraf,
    name: capitalize(AgentType.Telegraf)
  },
  {
    id: AgentType.CMA,
    name: labelCMA
  }
];
