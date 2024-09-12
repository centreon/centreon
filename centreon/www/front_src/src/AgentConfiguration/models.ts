import { SelectEntry } from '@centreon/ui';

export enum AgentType {
  Telegraf = 'telegraf'
}

export interface AgentConfigurationListing {
  id: number;
  name: string;
  type: AgentType;
  pollers: Array<SelectEntry>;
}
